<?php

namespace App\Application\Deposit\DTO;

final readonly class DetectedTransactionDTO
{
    public function __construct(
        public int $userId,
        public int $currencyId,
        public int $networkId,
        public int $currencyNetworkId,
        public int $walletAddressId,
        public string $externalKey,
        public string $txid,
        public string $amount,
        public string $toAddress,
        public ?string $fromAddress = null,
        public ?string $blockHash = null,
        public ?int $blockNumber = null,
        public int $confirmations = 0,
        public array $metadata = [],
    ) {}
}
