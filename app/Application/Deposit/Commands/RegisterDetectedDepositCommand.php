<?php

namespace App\Application\Deposit\Commands;

final readonly class RegisterDetectedDepositCommand
{
    public function __construct(
        public int $userId,
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
        public string $assetType = 'native',
        public ?string $contractAddress = null,
        public array $metadata = [],
    ) {}
}
