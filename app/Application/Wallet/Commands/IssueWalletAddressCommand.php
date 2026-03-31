<?php

namespace App\Application\Wallet\Commands;

class IssueWalletAddressCommand
{
    public function __construct(
        public int $userId,
        public int $networkId,
        public string $networkCode,
        public int $currencyNetworkId
    ) {}
}
