<?php

namespace App\Domain\Deposit\DTO;

final readonly class TokenContractDescriptor
{
    public function __construct(
        public int $currencyNetworkId,
        public int $currencyId,
        public string $currencyCode,
        public string $contractAddress,
        public int $decimals,
        public string $standard, // erc20 | trc20
    ) {}
}
