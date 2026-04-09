<?php

namespace App\Infrastructure\Blockchain\Clients;

use App\Application\Deposit\DTO\DetectedBlockchainEvent;
use App\Domain\Deposit\DTO\TokenContractDescriptor;
use App\Infrastructure\Blockchain\Contracts\BlockchainClient;
use Illuminate\Support\Facades\Http;

final class TronClient implements BlockchainClient
{
    public function __construct(
        private readonly string $rpcUrl,
        private readonly int $networkId,
    ) {}

    public function headBlock(): int
    {
        $response = Http::timeout(20)->retry(2, 200)->get(rtrim($this->rpcUrl, '/') . '/wallet/getnowblock');

        if (! $response->successful()) {
            throw new \RuntimeException('TRON head block request failed.');
        }

        return (int) data_get($response->json(), 'block_header.raw_data.number', 0);
    }

    public function blockHash(int $blockNumber): string
    {
        $block = $this->blockByNumber($blockNumber);

        return (string) data_get($block, 'blockID', '');
    }

    public function scanBlock(int $blockNumber, array $tokenContracts = []): array
    {
        $events = [];
        $block = $this->blockByNumber($blockNumber);
        $blockHash = (string) data_get($block, 'blockID', '');
        $transactions = data_get($block, 'transactions', []);

        if (! is_array($transactions)) {
            return [];
        }

        foreach ($transactions as $tx) {
            $txid = (string) data_get($tx, 'txID', '');
            $contractType = (string) data_get($tx, 'raw_data.contract.0.type', '');
            $value = data_get($tx, 'raw_data.contract.0.parameter.value', []);

            // Native TRX transfer
            if ($contractType === 'TransferContract') {
                $toAddress = (string) data_get($value, 'to_address', '');
                $fromAddress = (string) data_get($value, 'owner_address', '');
                $amountSun = (string) data_get($value, 'amount', '0');

                $events[] = new DetectedBlockchainEvent(
                    networkId: $this->networkId,
                    txid: $txid,
                    externalKey: $txid . ':0',
                    amount: $this->sunToTrx($amountSun),
                    toAddress: $this->normalizeTronAddress($toAddress),
                    fromAddress: $this->normalizeTronAddress($fromAddress),
                    blockHash: $blockHash,
                    blockNumber: $blockNumber,
                    confirmations: 1,
                    assetType: 'native',
                    contractAddress: null,
                    metadata: [
                        'source' => 'tron',
                        'kind' => 'native_transfer',
                        'amount_sun' => $amountSun,
                    ],
                );
            }

            // TRC20 transfer
            if ($contractType === 'TriggerSmartContract') {
                $receipt = $this->transactionReceipt($txid);
                $logs = data_get($receipt, 'log', []);

                if (! is_array($logs)) {
                    continue;
                }

                foreach ($logs as $index => $log) {
                    $contractAddress = strtolower((string) data_get($log, 'address', ''));
                    $descriptor = $this->findTokenDescriptor($tokenContracts, $contractAddress);

                    if (! $descriptor) {
                        continue;
                    }

                    $topics = (array) data_get($log, 'topics', []);
                    $recipient = $this->tronTopicToAddress((string) ($topics[2] ?? ''));
                    $sender = $this->tronTopicToAddress((string) ($topics[1] ?? ''));
                    $rawData = (string) data_get($log, 'data', '0x0');

                    $events[] = new DetectedBlockchainEvent(
                        networkId: $this->networkId,
                        txid: $txid,
                        externalKey: $txid . ':' . $index,
                        amount: $this->hexToDecimalString($rawData, $descriptor->decimals),
                        toAddress: $recipient,
                        fromAddress: $sender,
                        blockHash: $blockHash,
                        blockNumber: $blockNumber,
                        confirmations: 1,
                        assetType: 'trc20',
                        contractAddress: $contractAddress,
                        metadata: [
                            'source' => 'tron',
                            'kind' => 'trc20_transfer',
                            'log_index' => $index,
                            'decimals' => $descriptor->decimals,
                            'currency_network_id' => $descriptor->currencyNetworkId,
                            'currency_id' => $descriptor->currencyId,
                            'currency_code' => $descriptor->currencyCode,
                        ],
                    );
                }
            }
        }

        return $events;
    }

    private function blockByNumber(int $blockNumber): array
    {
        $response = Http::timeout(20)->retry(2, 200)
            ->post(rtrim($this->rpcUrl, '/') . '/wallet/getblockbynum', ['num' => $blockNumber]);

        if (! $response->successful()) {
            throw new \RuntimeException("TRON block request failed for block={$blockNumber}");
        }

        return (array) $response->json();
    }

    private function transactionReceipt(string $txid): array
    {
        $response = Http::timeout(20)->retry(2, 200)
            ->post(rtrim($this->rpcUrl, '/') . '/wallet/gettransactioninfobyid', ['value' => $txid]);

        if (! $response->successful()) {
            throw new \RuntimeException("TRON receipt request failed for tx={$txid}");
        }

        return (array) $response->json();
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

    private function normalizeTronAddress(string $address): string
    {
        return trim($address);
    }

    private function tronTopicToAddress(string $topic): string
    {
        $topic = strtolower(trim($topic));
        $topic = preg_replace('/^0x/', '', $topic);

        return substr($topic, -34);
    }

    private function sunToTrx(string $sun): string
    {
        return bcdiv($sun, '1000000', 6);
    }

    private function hexToDecimalString(string $hex, int $decimals = 6): string
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

        return bcdiv($dec, bcpow('10', (string) $decimals, 0), $decimals);
    }
}
