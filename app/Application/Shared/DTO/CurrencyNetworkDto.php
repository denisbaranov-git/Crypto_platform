<?php

namespace App\Application\Shared\DTO;

readonly class CurrencyNetworkDto
{
    public function __construct(
        public int     $id,
        public int     $networkId,
        public int     $currencyId,
        public string  $networkCode,
        public string  $currencyCode,
        public int     $decimals,
        public ?string $contractAddress,
        public int     $minConfirmations,
        public float   $minDepositAmount,
        public float   $minWithdrawalAmount,
        public ?float  $maxWithdrawalAmount,
        public bool    $useFinality,
        public ?int    $finalizationBlocks,
        public ?float  $finalityThreshold,
        public bool    $isActive,
        public bool    $isDepositEnabled,
        public bool    $isWithdrawalEnabled,
    ) {}
}
