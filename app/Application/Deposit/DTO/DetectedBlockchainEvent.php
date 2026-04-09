<?php

namespace App\Application\Deposit\DTO;

/**
 * Унифицированный результат scanner'а.
 * Клиент сети ничего не знает о пользователе и кошельках.
 * Он сообщает только on-chain факт.
 */
final readonly class DetectedBlockchainEvent
{
    public function __construct(
        public int $networkId,
        public string $txid,
        public string $externalKey,
        public string $amount,
        public string $toAddress,
        public ?string $fromAddress,
        public ?string $blockHash,
        public ?int $blockNumber,
        public int $confirmations,
        public string $assetType, // native | erc20 | trc20
        public ?string $contractAddress = null,
        public array $metadata = [],
    ) {}
}
