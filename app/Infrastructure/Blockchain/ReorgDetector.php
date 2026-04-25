<?php

declare(strict_types=1);

namespace App\Infrastructure\Blockchain;

use App\Domain\Deposit\Events\DepositReorged;
use App\Application\Withdrawal\Commands\HandleWithdrawalReorgCommand;
use App\Application\Withdrawal\Handlers\HandleWithdrawalReorgHandler;
use App\Domain\Deposit\Repositories\DepositRepository;
use App\Domain\Shared\Outbox\OutboxRepository;
use App\Domain\Shared\ValueObjects\BlockNumber;
use App\Domain\Withdrawal\Repositories\WithdrawalRepository;
use App\Infrastructure\Blockchain\Contracts\BlockchainClient;
use App\Infrastructure\Blockchain\Repositories\NetworkScannerCursorRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Unified chain reorg detector.
 *
 * One shared network cursor is the source of truth for the canonical chain state.
 * This detector rewinds deposits and withdrawals together so the system never
 * observes "deposit rewound but withdrawal not yet" as a stable state.
 */
final class ReorgDetector
{
    public function __construct(
        private readonly NetworkScannerCursorRepository $cursors,
        private readonly DepositRepository $depositRepository,
        private readonly WithdrawalRepository $withdrawalRepository,
        private readonly OutboxRepository $outbox,
        private readonly HandleWithdrawalReorgHandler $withdrawalReorgHandler,
    ) {}

    /**
     * Detects reorg by comparing canonical hash of the last processed block
     * with the stored hash in the shared network cursor.
     *
     * If mismatch is found:
     * - rewind cursor by safety window
     * - rewind deposits in that range
     * - rewind withdrawals in that range
     * - all inside one DB transaction
     */
    public function detectAndRewind(
        int $networkId,
        BlockchainClient $client,
        int $reorgWindowBlocks,
        string $networkCode
    ): bool {
        $cursor = $this->cursors->get($networkId);

        if ($cursor->last_processed_block <= 0 || ! $cursor->last_processed_block_hash) {
            return false;
        }

        $currentHash = $client->blockHash((int) $cursor->last_processed_block);

        if ($currentHash === '' || $currentHash === $cursor->last_processed_block_hash) {
            return false;
        }

        $oldLastProcessedBlock = (int) $cursor->last_processed_block;
        $rewindTo = max(0, $oldLastProcessedBlock - $reorgWindowBlocks);

        $affectedDeposits = $this->depositRepository->findByNetworkAndBlockNumberBetween(
            $networkId,
            $rewindTo + 1,
            $oldLastProcessedBlock
        );

        $affectedWithdrawals = $this->withdrawalRepository->findByNetworkAndBlockNumberBetween(
            $networkId,
            $rewindTo + 1,
            $oldLastProcessedBlock
        );

        DB::transaction(function () use (
            $affectedDeposits,
            $affectedWithdrawals,
            $networkId,
            $rewindTo,
            $oldLastProcessedBlock,
            $networkCode
        ): void {
            foreach ($affectedDeposits as $deposit) {
                $wasCredited = $deposit->isCredited();

                $deposit->markReorged(BlockNumber::fromInt($rewindTo));
                $saved = $this->depositRepository->save($deposit);

                if ($wasCredited && $saved->creditedOperationId() !== null) {
                    $this->outbox->append(
                        idempotencyKey: 'deposit:' . $saved->id()->value() . ':reorged:' . $rewindTo,
                        aggregateType: 'deposit',
                        aggregateId: (string) $saved->id()->value(),
                        eventType: DepositReorged::class,
                        payload: [
                            'depositId' => $saved->id()->value(),
                            'networkId' => $saved->networkId(),
                            'externalKey' => $saved->externalKey()->value(),
                            'rewindToBlock' => $rewindTo,
                            'oldLastProcessedBlock' => $oldLastProcessedBlock,
                            'creditedOperationId' => $saved->creditedOperationId(),
                        ]
                    );
                }
            }

            foreach ($affectedWithdrawals as $withdrawal) {
                $this->withdrawalReorgHandler->handle(
                    new HandleWithdrawalReorgCommand(
                        withdrawalId: $withdrawal->id()->value(),
                        reason: 'canonical_block_hash_mismatch',
                        metadata: [
                            'source' => 'reorg_detector',
                            'network_code' => $networkCode,
                            'rewind_to_block' => $rewindTo,
                            'old_last_processed_block' => $oldLastProcessedBlock,
//                            'confirmed_block_number' => $withdrawal->confirmedBlockNumber(),
//                            'confirmed_block_hash' => $withdrawal->confirmedBlockHash(),
                            'block_number' => $withdrawal->blockNumber(),
                            'block_hash' => $withdrawal->blockHash(),
                        ]
                    )
                );
            }

            $this->cursors->rewind($networkId, $rewindTo);
        });

        Log::channel('ops')->warning('Chain reorg rewind applied', [
            'network_id' => $networkId,
            'network_code' => $networkCode,
            'rewind_to_block' => $rewindTo,
            'old_last_processed_block' => $oldLastProcessedBlock,
            'affected_deposits' => count($affectedDeposits),
            'affected_withdrawals' => count($affectedWithdrawals),
        ]);

        return true;
    }
}
