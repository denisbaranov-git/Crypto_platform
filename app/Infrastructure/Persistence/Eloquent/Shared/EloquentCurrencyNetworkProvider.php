<?php

namespace App\Infrastructure\Persistence\Eloquent\Shared;

use App\Application\Shared\Contracts\CurrencyNetworkProviderInterface;
use App\Application\Shared\DTO\CurrencyNetworkDto;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentCurrencyNetwork;

class EloquentCurrencyNetworkProvider implements CurrencyNetworkProviderInterface
{
    public function findById(int $networkCurrencyId): ?CurrencyNetworkDto
    {
//        $model = EloquentCurrencyNetwork::find($networkCurrencyId);
        $model = EloquentCurrencyNetwork::query()
                    ->leftJoin('networks', 'networks.id', '=', 'currency_networks.network_id')
                    ->leftJoin('currencies', 'currencies.id', '=', 'currency_networks.currency_id')
                    ->select([
                        'currency_networks.*',
                        'networks.code as network_code',
                        'currencies.code as currency_code',
                    ])
                    ->where('currency_networks.id', $networkCurrencyId)->first();
        if (!$model) {
            return null;
        }

        return $this->toDto($model);
    }

    private function toDto(EloquentCurrencyNetwork $model): CurrencyNetworkDto
    {
        return new CurrencyNetworkDto(
            id: $model->id,
            networkId: $model->network_id,
            currencyId: $model->currency_id,
            networkCode: $model->network_code,
            currencyCode: $model->currency_code,
            decimals: $model->decimals,
            contractAddress: $model->contract_address,
            minConfirmations: $model->min_confirmations,
            minDepositAmount: (float)$model->min_deposit_amount,
            minWithdrawalAmount: (float)$model->min_withdrawal_amount,
            maxWithdrawalAmount: $model->max_withdrawal_amount ? (float)$model->max_withdrawal_amount : null,
            useFinality: $model->use_finality,
            finalizationBlocks: $model->finalization_blocks,
            finalityThreshold: $model->finality_threshold ? (float)$model->finality_threshold : null,
            isActive: $model->is_active,
            isDepositEnabled: $model->is_deposit_enabled,
            isWithdrawalEnabled: $model->is_withdrawal_enabled,
        );
    }
}
