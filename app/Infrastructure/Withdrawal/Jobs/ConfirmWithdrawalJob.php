<?php

declare(strict_types=1);

//namespace App\Jobs;
namespace App\Infrastructure\Withdrawal\Jobs;

use App\Application\Withdrawal\Commands\UpdateWithdrawalConfirmationsCommand;
use App\Application\Withdrawal\Handlers\UpdateWithdrawalConfirmationsHandler;
use App\Domain\Withdrawal\Repositories\WithdrawalRepository;
use App\Infrastructure\Blockchain\BlockchainClientFactory;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentNetwork;
use App\Infrastructure\Persistence\Eloquent\Repositories\NetworkScannerCursorRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * CHANGED:
 * - confirmation polling is similar to RefreshDepositConfirmationsJob;
 * - it checks outgoing txid statuses, not deposit facts.
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

            $tx = $client->transaction($withdrawal->txid()->value()); //!!!!!!! need for EVM externalKey: (string) ($tx['hash'] ?? '') . ':0',
                                                                        //  tron   externalKey:        $txid = (string) data_get($tx, 'txID', '');   externalKey: $txid . ':0',
                                                                    // bitcoin externalKey: $txid . ':' . $n, ..............
            if ($tx === null) {
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
