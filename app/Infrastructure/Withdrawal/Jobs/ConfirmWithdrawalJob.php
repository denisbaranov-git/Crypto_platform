<?php

declare(strict_types=1);

//namespace App\Jobs;
namespace App\Infrastructure\Withdrawal\Jobs;

use App\Application\Withdrawal\Commands\UpdateWithdrawalConfirmationsCommand;
use App\Application\Withdrawal\Handlers\UpdateWithdrawalConfirmationsHandler;
use App\Domain\Withdrawal\Repositories\WithdrawalRepository;
use App\Infrastructure\Blockchain\BlockchainClientFactory;
use App\Infrastructure\Blockchain\ReorgDetector;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentNetwork;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

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
        ReorgDetector $reorgDetector,
        WithdrawalRepository $withdrawals,
        UpdateWithdrawalConfirmationsHandler $updateHandler,
    ): void {
        $network = EloquentNetwork::query()->findOrFail($this->networkId);
        $client = $clientFactory->forNetwork($network->id);

        $networkConfig = config("blockchain.scanner.networks.{$network->code}", []);
        $reorgWindow = (int) ($networkConfig['reorg_window_blocks'] ?? 50);

        // Shared chain reorg check.
        if ($reorgDetector->detectAndRewind($network->id, $client, $reorgWindow, $network->code)) {
            return;
        }

        $openWithdrawals = $withdrawals->findOpenByNetwork($network->id, 500);

        foreach ($openWithdrawals as $withdrawal) {
            if ($withdrawal->txid() === null) {
                continue;
            }

            try {
                $tx = $client->transaction($withdrawal->txid()->value());

                // tx can be temporarily unavailable - this is not a reorg by itself.
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
                    actualFeeAmount: $tx->actualFeeAmount,
                    feeCurrencyCode: $tx->feeCurrencyCode,
                ));
            } catch (Throwable $e) {
                Log::channel('ops')->warning('ConfirmWithdrawalJob failed while polling tx', [
                    'network_id' => $network->id,
                    'network_code' => $network->code,
                    'withdrawal_id' => $withdrawal->id()?->value(),
                    'txid' => $withdrawal->txid()?->value(),
                    'exception' => $e::class,
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }
        }
    }
}
