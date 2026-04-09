<?php

namespace App\Infrastructure\Deposit\Services;

use App\Domain\Deposit\DTO\TokenContractDescriptor;
use App\Domain\Deposit\Services\CurrencyNetworkQueryService;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentCurrencyNetwork;

final class EloquentCurrencyNetworkQueryService implements CurrencyNetworkQueryService
{
    public function activeTokenContractsForNetwork(int $networkId): array
    {
        return EloquentCurrencyNetwork::query()
            ->where('network_id', $networkId)
            ->where('is_active', true)
            ->whereNotNull('contract_address')
            ->with('currency:id,code')
            ->get()
            ->map(function (EloquentCurrencyNetwork $row) {
                return new TokenContractDescriptor(
                    currencyNetworkId: (int) $row->id,
                    currencyId: (int) $row->currency_id,
                    currencyCode: (string) $row->currency->code,
                    contractAddress: strtolower((string) $row->contract_address),
                    decimals: (int) $row->decimals,
                    standard: $this->guessStandard((string) $row->network->rpc_driver),
                );
            })
            ->all();
    }

    private function guessStandard(string $rpcDriver): string
    {
        return match ($rpcDriver) {
            'tron' => 'trc20',
            default => 'erc20',
        };
    }
}
