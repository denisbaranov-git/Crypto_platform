<?php

declare(strict_types=1);

namespace App\Infrastructure\Blockchain\Jobs;

use App\Application\Deposit\Commands\RegisterDetectedDepositCommand;
use App\Application\Deposit\Commands\UpdateDepositConfirmationsCommand;
use App\Application\Deposit\Handlers\RegisterDetectedDepositHandler;
use App\Application\Deposit\Handlers\UpdateDepositConfirmationsHandler;
use App\Domain\Deposit\Services\CurrencyNetworkQueryService;
use App\Infrastructure\Blockchain\BlockchainClientFactory;
use App\Infrastructure\Blockchain\ReorgDetector;
use App\Infrastructure\Blockchain\Repositories\NetworkScannerCursorRepository;

use App\Infrastructure\Persistence\Eloquent\Models\EloquentCurrency;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentCurrencyNetwork;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentNetwork;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentWalletAddress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

final class ScanNetworkBlocksJob_old implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 180;

    public function __construct(
        public int $networkId,
    ) {}

    public function handle(
        BlockchainClientFactory $clientFactory,
        NetworkScannerCursorRepository $cursors,
        CurrencyNetworkQueryService $currencyNetworkQuery,
        ReorgDetector $reorgDetector,
        RegisterDetectedDepositHandler $registerHandler,
        UpdateDepositConfirmationsHandler $updateHandler,
    ): void {
        $network = EloquentNetwork::query()->findOrFail($this->networkId);
        $cursor = $cursors->get($this->networkId);

        $networkConfig = config("blockchain.scanner.networks.{$network->code}", []);
        $scanInterval = (int) ($networkConfig['scan_interval_seconds'] ?? 60);
        $safetyMargin = (int) ($networkConfig['safety_margin_blocks'] ?? config('blockchain.scanner.default_safety_margin_blocks', 12));
        $reorgWindow = (int) ($networkConfig['reorg_window_blocks'] ?? 50);

        // Throttle: job может запускаться каждую минуту, но не всегда должна сканировать.
        if ($cursor->scanned_at && $cursor->scanned_at->diffInSeconds(now()) < $scanInterval) {
            return;
        }

        $client = $clientFactory->forNetwork($network->id);
        // denis // лучше потом вынести в отдельный DetectChainReorgJob
        // 1) Проверяем reorg только по последнему зафиксированному блоку.
        if ($reorgDetector->detectAndRewind($network->id, $client, $reorgWindow)) {
            return;
        }

        $head = $client->headBlock();
        $safeHead = max(0, $head - $safetyMargin);

        if ($cursor->last_processed_block >= $safeHead) {
            $cursor->scanned_at = now();
            $cursor->save();

            return;
        }

        $tokenContracts = $currencyNetworkQuery->activeTokenContractsForNetwork($network->id);

        for ($blockNumber = $cursor->last_processed_block + 1; $blockNumber <= $safeHead; $blockNumber++) {
            $events = $client->scanBlock($blockNumber, $tokenContracts);

            foreach ($events as $event) {
                $walletContext = $this->resolveWalletContext($network->id, $event->toAddress);

                if (! $walletContext) {
                    continue;
                }

                $currencyCode = $event->metadata['currency_code'] ?? $network->native_currency_code;
                $currency = EloquentCurrency::query()->where('code', $currencyCode)->first();

                if (! $currency) {
                    continue; // Валюта не поддерживается.
                }

                $currencyNetwork = EloquentCurrencyNetwork::query()
                    ->where('network_id', $network->id)
                    ->where('currency_id', $currency->id)
                    ->where(function ($q) use ($event) {
                        if ($event->assetType === 'native') {
                            $q->whereNull('contract_address');
                        } else {
                            $q->where('contract_address', strtolower((string) $event->contractAddress));
                        }
                    })
                    ->first();

                if (! $currencyNetwork) {
                    continue;
                }

                // 2) Регистрируем факт депозита.
                $registerHandler->handle(new RegisterDetectedDepositCommand(
                    userId: $walletContext['user_id'],
                    networkId: $network->id,
                    currencyNetworkId: $currencyNetwork->id,
                    walletAddressId: $walletContext['wallet_address_id'],
                    externalKey: $event->externalKey,
                    txid: $event->txid,
                    amount: $event->amount,
                    toAddress: $event->toAddress,
                    fromAddress: $event->fromAddress,
                    blockHash: $event->blockHash,
                    blockNumber: $event->blockNumber,
                    confirmations: $event->confirmations,
                    assetType: $event->assetType,
                    contractAddress: $event->contractAddress,
                    metadata: $event->metadata,
                ));

                // 3) Обновляем confirmations.
                $updateHandler->handle(new UpdateDepositConfirmationsCommand(
                    networkId: $network->id,
                    externalKey: $event->externalKey,
                    currencyNetworkId: $currencyNetwork->id,
                    amount: $event->amount,
                    confirmations: $event->confirmations,
                    fromAddress: $event->fromAddress,
                    toAddress: $event->toAddress,
                    blockHash: $event->blockHash,
                    blockNumber: $event->blockNumber,
                    finalized: $event->metadata['finalized'] ?? null,
                    metadata: $event->metadata,
                ));
            }

            $cursors->advance($network->id, $blockNumber, $client->blockHash($blockNumber));
        }
    }
    /**
     * Простая address lookup логика.
     * В продакшне это можно вынести в отдельный query service,
     * но для v1 такой подход читается легче и не плодит абстракции.
     */
    private function resolveWalletContext(int $networkId, string $address): ?array
    {
        $walletAddress = EloquentWalletAddress::query()
            ->where('network_id', $networkId)
            ->where('address', $address)
            ->where('is_active', true)
            ->with(['wallet'])
            ->first();

        if (! $walletAddress || ! $walletAddress->wallet) {
            return null;
        }

        return [
            'user_id' => (int) $walletAddress->wallet->user_id,
            'wallet_address_id' => (int) $walletAddress->id,
        ];
    }
}
