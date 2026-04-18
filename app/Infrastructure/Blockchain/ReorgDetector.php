<?php

namespace App\Infrastructure\Blockchain;

use App\Domain\Deposit\Events\DepositReorged;
use App\Domain\Deposit\Repositories\DepositRepository;
use App\Domain\Shared\Outbox\OutboxRepository;
use App\Domain\Shared\ValueObjects\BlockNumber;
use App\Infrastructure\Blockchain\Contracts\BlockchainClient;
use App\Infrastructure\Persistence\Eloquent\Repositories\NetworkScannerCursorRepository;
use Illuminate\Support\Facades\DB;

final class ReorgDetector
{
    public function __construct(
        private readonly NetworkScannerCursorRepository $cursors,
        private readonly DepositRepository              $depositRepository,
        private readonly OutboxRepository               $outbox,
    )
    {
    }

    /**
     * Проверяем только последний обработанный блок.
     * Это простая и очень практичная защита:
     * если hash старого блока не совпал, значит chain reorganized.
     */
    public function detectAndRewind(
        int              $networkId,
        BlockchainClient $client,
        int              $reorgWindowBlocks
    ): bool
    {

        $cursor = $this->cursors->get($networkId);

        if ($cursor->last_processed_block <= 0 || !$cursor->last_processed_block_hash) {
            return false;
        }

        $currentHash = $client->blockHash($cursor->last_processed_block);

        if ($currentHash === $cursor->last_processed_block_hash) {
            return false;
        }

        $oldLastProcessedBlock = $cursor->last_processed_block;
        $rewindTo = max(0, $oldLastProcessedBlock - $reorgWindowBlocks);

        $affectedDeposits = $this->depositRepository->findByNetworkAndBlockNumberBetween(
            $networkId,
            $rewindTo + 1,
            $oldLastProcessedBlock
        );

        DB::transaction(function () use (
            $affectedDeposits,
            $networkId,
            $rewindTo,
            $oldLastProcessedBlock
        ): void {
            foreach ($affectedDeposits as $deposit) {
                $wasCredited = $deposit->isCredited();

                $deposit->markReorged(BlockNumber::fromInt($rewindTo));
                $saved = $this->depositRepository->save($deposit);

                if ($wasCredited && $saved->creditedOperationId() !== null) {
                    $this->outbox->append(
                        idempotencyKey: 'deposit:' . $saved->id()->value() . ':reorged:' . $rewindTo,
                        aggregateType: 'deposit',
                        aggregateId: (string)$saved->id()->value(),
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

            $this->cursors->rewind($networkId, $rewindTo);
        });//transaction

        return true;
    }
}
