<?php

namespace App\Application\Deposit\Commands;

final readonly class UpdateDepositConfirmationsCommand
{
    public function __construct(
        public int $networkId,
        public string $externalKey,
        public int $currencyNetworkId,
        public string $amount,
        public int $confirmations,
        public ?string $fromAddress = null,
        public ?string $toAddress = null,
        public ?string $blockHash = null,
        public ?int $blockNumber = null,
        public ?bool $finalized = null,
        public array $metadata = [],
    ) {}
}
