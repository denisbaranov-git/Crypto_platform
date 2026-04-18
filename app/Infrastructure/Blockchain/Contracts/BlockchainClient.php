<?php

namespace App\Infrastructure\Blockchain\Contracts;

use App\Application\Deposit\DTO\DetectedBlockchainEvent;
use App\Domain\Deposit\DTO\TokenContractDescriptor;
use App\Infrastructure\Blockchain\DTO\BlockchainTransactionStatus;

interface BlockchainClient
{
    public function headBlock(): int;

    public function blockHash(int $blockNumber): string;

    /**
     * @param TokenContractDescriptor[] $tokenContracts
     * @return DetectedBlockchainEvent[]
     */
    public function scanBlock(int $blockNumber, array $tokenContracts = []): array;
    public function transaction(string $txid): ?BlockchainTransactionStatus;

    //new methods
    public function send(string $txid): ?BlockchainTransactionStatus;

    public function getNativeBalance(string $address): string;
    /**
     * Получить баланс токена (TRC-20)
     * @param string $address
     * @param string $contractAddress
     * @return string
     */
    public function getTokenBalance(string $address, string $contractAddress): string;

    /**
     * Отправить нативные TRX
     */
    public function sendNative(string $fromPrivateKey, string $to, float $amount): string;

    /**
     * Отправить TRC-20 токен (например, USDT на Tron)
     */
    public function sendToken(string $fromPrivateKey, string $to, float $amount, string $contractAddress, int $decimals): string;

    /**
     * Получить входящие транзакции токена
     */
    public function getIncomingTokenTransactions(string $address, string $contractAddress, int $fromBlock, int $toBlock): array;

    /**
     * Получить номер последнего блока
     */
    public function getLatestBlock(): int;

    /**
     * Получить данные последнего блока
     */
    public function getLatestBlockData(): array;

    /**
     * Получить данные блока по номеру
     */
    public function getBlockByNumber(int $blockNumber): ?array;


    /**
     * Получить receipt транзакции
     */
    public function getTransactionReceipt(string $txid): ?array;
}
