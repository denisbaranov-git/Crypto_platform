<?php

namespace App\Domain\Deposit\Services;

interface CurrencyNetworkQueryService
{
    public function activeTokenContractsForNetwork(int $networkId): array;
}
