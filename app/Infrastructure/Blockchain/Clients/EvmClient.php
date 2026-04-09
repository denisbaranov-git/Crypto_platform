<?php

namespace App\Infrastructure\Blockchain\Clients;

use App\Application\Deposit\DTO\DetectedBlockchainEvent;
use App\Domain\Deposit\DTO\TokenContractDescriptor;
use App\Infrastructure\Blockchain\Contracts\BlockchainClient;
use App\Infrastructure\Blockchain\Support\JsonRpcClient;

final class EvmClient implements BlockchainClient
{
    public function __construct(
        private readonly JsonRpcClient $rpc,
        private readonly int $networkId,
    ) {}

    public function headBlock(): int
    {
        $hex = (string) $this->rpc->call('eth_blockNumber');
        return hexdec($hex);
    }

    public function blockHash(int $blockNumber): string
    {
        $hex = '0x' . dechex($blockNumber);
        $block = (array) $this->rpc->call('eth_getBlockByNumber', [$hex, true]);

        return (string) ($block['hash'] ?? '');
    }

    /**
     * Сканируем:
     * 1) native transfers из tx list
     * 2) ERC20 transfers из logs для известных contract addresses
     */
    public function scanBlock(int $blockNumber, array $tokenContracts = []): array
    {
        $events = [];

        $hexBlock = '0x' . dechex($blockNumber);
        $block = (array) $this->rpc->call('eth_getBlockByNumber', [$hexBlock, true]);

        $blockHash = (string) ($block['hash'] ?? '');
        $txs = $block['transactions'] ?? [];

        foreach ($txs as $tx) {
            $to = isset($tx['to']) ? $this->normalizeAddress((string) $tx['to']) : null;

            if (! $to) {
                continue;
            }

            $value = (string) ($tx['value'] ?? '0x0');
            $events[] = new DetectedBlockchainEvent(
                networkId: $this->networkId,
                txid: (string) ($tx['hash'] ?? ''),
                externalKey: (string) ($tx['hash'] ?? '') . ':0',
                amount: $this->hexToDecimalString($value),
                toAddress: $to,
                fromAddress: isset($tx['from']) ? $this->normalizeAddress((string) $tx['from']) : null,
                blockHash: $blockHash,
                blockNumber: $blockNumber,
                confirmations: 1,
                assetType: 'native',
                contractAddress: null,
                metadata: [
                    'source' => 'evm',
                    'kind' => 'native_transfer',
                ],
            );
        }

        $contractAddresses = array_values(array_unique(array_map(
            fn (TokenContractDescriptor $t) => strtolower($t->contractAddress),
            $tokenContracts
        )));

        if (! empty($contractAddresses)) {
            $logs = (array) $this->rpc->call('eth_getLogs', [[
                'fromBlock' => $hexBlock,
                'toBlock'   => $hexBlock,
                'address'   => $contractAddresses,
                'topics'    => [
                    config('deposit.evm.transfer_signature'),
                ],
            ]]);

            foreach ($logs as $log) {
                $contractAddress = strtolower((string) ($log['address'] ?? ''));
                $txid = (string) ($log['transactionHash'] ?? '');
                $logIndex = isset($log['logIndex']) ? hexdec((string) $log['logIndex']) : 0;

                $recipient = $this->topicToAddress((string) ($log['topics'][2] ?? ''));
                $sender = $this->topicToAddress((string) ($log['topics'][1] ?? ''));
                $descriptor = $this->findTokenDescriptor($tokenContracts, $contractAddress);

                if (! $descriptor) {
                    continue;
                }

                $events[] = new DetectedBlockchainEvent(
                    networkId: $this->networkId,
                    txid: $txid,
                    externalKey: $txid . ':' . $logIndex,
                    amount: $this->hexToDecimalString((string) ($log['data'] ?? '0x0'), $descriptor->decimals),
                    toAddress: $recipient,
                    fromAddress: $sender,
                    blockHash: $blockHash,
                    blockNumber: $blockNumber,
                    confirmations: 1,
                    assetType: 'erc20',
                    contractAddress: $contractAddress,
                    metadata: [
                        'source' => 'evm',
                        'kind' => 'erc20_transfer',
                        'log_index' => $logIndex,
                        'decimals' => $descriptor->decimals,
                        'currency_network_id' => $descriptor->currencyNetworkId,
                        'currency_id' => $descriptor->currencyId,
                        'currency_code' => $descriptor->currencyCode,
                    ],
                );
            }
        }

        return $events;
    }

    private function findTokenDescriptor(array $tokenContracts, string $contractAddress): ?TokenContractDescriptor
    {
        foreach ($tokenContracts as $descriptor) {
            if (strtolower($descriptor->contractAddress) === strtolower($contractAddress)) {
                return $descriptor;
            }
        }

        return null;
    }

    private function normalizeAddress(string $value): string
    {
        $value = strtolower(trim($value));
        return str_starts_with($value, '0x') ? $value : '0x' . $value;
    }

    private function topicToAddress(string $topic): string
    {
        $topic = strtolower(trim($topic));
        $topic = preg_replace('/^0x/', '', $topic);

        return '0x' . substr($topic, -40);
    }

    private function hexToDecimalString(string $hex, int $decimals = 18): string
    {
        $hex = strtolower(trim($hex));
        $hex = preg_replace('/^0x/', '', $hex);

        if ($hex === '') {
            return '0';
        }

        $dec = '0';
        foreach (str_split($hex) as $digit) {
            $value = (string) hexdec($digit);
            $dec = bcadd(bcmul($dec, '16', 0), $value, 0);
        }

        if ($decimals <= 0) {
            return $dec;
        }

        return bcdiv($dec, bcpow('10', (string) $decimals, 0), $decimals);
    }
}
