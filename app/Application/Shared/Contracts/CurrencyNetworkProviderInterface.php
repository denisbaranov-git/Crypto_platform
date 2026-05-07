<?php

namespace App\Application\Shared\Contracts;

use App\Application\Shared\DTO\CurrencyNetworkDto;

interface CurrencyNetworkProviderInterface
{
    /**
     * @return CurrencyNetworkDto|null
     */
    public function findById(int $networkCurrencyId): ?CurrencyNetworkDto;

    /**
     * @return CurrencyNetworkDto[]
     */
//    public function getActiveNetworksForCurrency(int $currencyId): array;

//    public function getWithdrawalConfig(int $networkCurrencyId): WithdrawalConfigDto;
}
