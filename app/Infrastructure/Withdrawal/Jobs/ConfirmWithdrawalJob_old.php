<?php

declare(strict_types=1);

//namespace App\Jobs;
namespace App\Infrastructure\Withdrawal\Jobs;
use App\Application\Withdrawal\Commands\HandleWithdrawalReorgCommand;
use App\Application\Withdrawal\Commands\UpdateWithdrawalConfirmationsCommand;
use App\Application\Withdrawal\Handlers\HandleWithdrawalReorgHandler;
use App\Application\Withdrawal\Handlers\UpdateWithdrawalConfirmationsHandler;
use App\Domain\Withdrawal\Repositories\WithdrawalRepository;
use App\Infrastructure\Blockchain\BlockchainClientFactory;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentNetwork;
use App\Infrastructure\Blockchain\Repositories\NetworkScannerCursorRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * CHANGED:
 * - polls tx status by txid;
 * - stores/updates snapshot block data;
 * - detects canonical mismatch as reorg.
 */
final class ConfirmWithdrawalJob_old implements ShouldQueue
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
        HandleWithdrawalReorgHandler $reorgHandler,
    ): void {
        $network = EloquentNetwork::query()->findOrFail($this->networkId);
        $cursor = $cursors->get($network->id);

        $networkConfig = config("blockchain.scanner.networks.{$network->code}", []);
        $scanInterval = (int) ($networkConfig['scan_interval_seconds'] ?? config('blockchain.scanner.default_scan_interval_seconds', 30));

        if ($cursor->scanned_at && $cursor->scanned_at->diffInSeconds(now()) < $scanInterval) {
            return;
        }

        $client = $clientFactory->forNetwork($network->id);
        $openWithdrawals = $withdrawals->findOpenByNetwork($network->id, 500);

        foreach ($openWithdrawals as $withdrawal) { //loop for Withdrawals  with status =  ['broadcasted', 'settled']
            if ($withdrawal->txid() === null) {
                continue;
            }

            $tx = $client->transaction($withdrawal->txid()->value());
            if ($tx === null) {
                if ($withdrawal->confirmedBlockNumber() !== null && $withdrawal->confirmedBlockHash() !== null) {
                    $canonicalHash = $client->blockHash($withdrawal->confirmedBlockNumber());

                    if ($canonicalHash !== '' && $canonicalHash !== $withdrawal->confirmedBlockHash()) {
                        $reorgHandler->handle(new HandleWithdrawalReorgCommand(
                            withdrawalId: $withdrawal->id()->value(),
                            reason: 'canonical_block_hash_mismatch',
                            metadata: [
                                'source' => 'confirm_withdrawal_job',
                                'network_code' => $network->code,
                            ]
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
                confirmations: $tx->confirmations,
                blockHash: $tx->blockHash,
                blockNumber: $tx->blockNumber,
                finalized: $tx->finalized,
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
