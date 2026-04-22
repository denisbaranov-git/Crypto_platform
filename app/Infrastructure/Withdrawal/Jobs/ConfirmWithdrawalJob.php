<?php

declare(strict_types=1);

//namespace App\Jobs;
namespace App\Infrastructure\Withdrawal\Jobs;

use App\Application\Withdrawal\Commands\UpdateWithdrawalConfirmationsCommand;
use App\Application\Withdrawal\Handlers\UpdateWithdrawalConfirmationsHandler;
use App\Domain\Withdrawal\Repositories\WithdrawalRepository;
use App\Infrastructure\Blockchain\BlockchainClientFactory;
use App\Infrastructure\Blockchain\Repositories\NetworkScannerCursorRepository;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentNetwork;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * CHANGED:
 * - confirmation polling for outgoing txs;
 * - also detects reorg by comparing stored confirmed block hash with canonical block hash.
 */
final class ConfirmWithdrawalJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(
        public int $networkId,
    ) {}

    public function handle(
        BlockchainClientFactory $clientFactory,
        NetworkScannerCursorRepository $cursors,
        WithdrawalRepository $withdrawals,
        UpdateWithdrawalConfirmationsHandler $updateHandler,
    ): void {
        $network = EloquentNetwork::query()->findOrFail($this->networkId);
        $cursor = $cursors->get($network->id);

        $networkConfig = config("withdrawal.scanner.networks.{$network->code}", []);
        $scanInterval = (int) ($networkConfig['scan_interval_seconds'] ?? config('withdrawal.scanner.default_scan_interval_seconds', 30));

        if ($cursor->scanned_at && $cursor->scanned_at->diffInSeconds(now()) < $scanInterval) {
            return;
        }

        $client = $clientFactory->forNetwork($network->id);
        $openWithdrawals = $withdrawals->findOpenByNetwork($network->id, 500);

        foreach ($openWithdrawals as $withdrawal) {
            if ($withdrawal->txid() === null) {
                continue;
            }

            $status = $client->transaction($withdrawal->txid()->value());

            if ($status === null) {
                // If we already have a confirmed snapshot, check canonical block hash.
                if ($withdrawal->confirmedBlockNumber() !== null && $withdrawal->confirmedBlockHash() !== null) {
                    $canonicalHash = $client->blockHash($withdrawal->confirmedBlockNumber());

                    if ($canonicalHash !== '' && $canonicalHash !== $withdrawal->confirmedBlockHash()) { // reorg detect!!! -> HandleWithdrawalReorgHandler ->reversal
                        $updateHandler->handle(new UpdateWithdrawalConfirmationsCommand(
                            withdrawalId: $withdrawal->id()->value(),
                            networkId: $network->id,
                            currencyNetworkId: $withdrawal->currencyNetworkId(),
                            txid: $withdrawal->txid()->value(),
                            confirmations: 0,
                            blockHash: null,
                            blockNumber: null,
                            finalized: false,
                            metadata: [
                                'source' => 'confirm_withdrawal_job',
                                'reorg_detected' => true,
                                'reason' => 'canonical_block_hash_mismatch',
                            ],
                        ));
                    }
                }

                continue;
            }

            $updateHandler->handle(new UpdateWithdrawalConfirmationsCommand(
                withdrawalId: $withdrawal->id()->value(),
                networkId: $network->id,
                currencyNetworkId: $withdrawal->currencyNetworkId(),
                txid: $withdrawal->txid()->value(),
                confirmations: $status->confirmations,
                blockHash: $status->blockHash,
                blockNumber: $status->blockNumber,
                finalized: $status->finalized,
                metadata: [
                    'source' => 'confirm_withdrawal_job',
                    'network_code' => $network->code,
                ],
            ));
        }

        $cursors->touch($network->id, [
            'scanned_at' => now(),
        ]);
    }
}
