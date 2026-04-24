<?php

declare(strict_types=1);

namespace App\Infrastructure\Blockchain\Clients;

use App\Application\Deposit\DTO\DetectedBlockchainEvent;
use App\Domain\Deposit\DTO\TokenContractDescriptor;
use App\Domain\Withdrawal\Entities\Withdrawal;
use App\Infrastructure\Blockchain\Contracts\BlockchainClient;
use App\Infrastructure\Blockchain\DTO\BlockchainTransactionStatus;
use App\Infrastructure\Blockchain\DTO\PreparedWithdrawalTransaction;
use App\Infrastructure\Blockchain\Support\JsonRpcClient;
use App\Infrastructure\Blockchain\Services\SystemWalletSecretResolver;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentCurrencyNetwork;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentNetwork;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentSystemWallet;
use DomainException;
use Web3p\EthereumTx\Transaction;

/**
 * EVM:
 * - native scan via tx list
 * - ERC20 scan via Transfer logs
 * - withdrawal signing via local private key
 * - broadcast via eth_sendRawTransaction
 */
final class EvmClient implements BlockchainClient
{
    public function __construct(
        private readonly JsonRpcClient $rpc,
        private readonly int $networkId,
        private readonly SystemWalletSecretResolver $secrets,
    ) {}

    public function headBlock(): int
    {
        $hex = (string) $this->rpc->call('eth_blockNumber');

        return hexdec($this->normalizeHex($hex));
    }

    public function blockHash(int $blockNumber): string
    {
        $hexBlock = $this->toHexQuantity($blockNumber);
        $block = (array) $this->rpc->call('eth_getBlockByNumber', [$hexBlock, false]);

        return (string) ($block['hash'] ?? '');
    }

    /**
     * @return array<int, DetectedBlockchainEvent>
     */
    public function scanBlock(int $blockNumber, array $tokenContracts = []): array
    {
        $events = [];
        $hexBlock = $this->toHexQuantity($blockNumber);
        $block = (array) $this->rpc->call('eth_getBlockByNumber', [$hexBlock, true]);

        $blockHash = (string) ($block['hash'] ?? '');
        $txs = $block['transactions'] ?? [];

        foreach ($txs as $tx) {
            $to = isset($tx['to']) ? $this->normalizeAddress((string) $tx['to']) : null;
            if (! $to) {
                continue;
            }

            $txid = (string) ($tx['hash'] ?? '');
            $value = (string) ($tx['value'] ?? '0x0');

            $events[] = new DetectedBlockchainEvent(
                networkId: $this->networkId,
                txid: $txid,
                externalKey: $txid . ':0',
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

                $descriptor = $this->findTokenDescriptor($tokenContracts, $contractAddress);
                if (! $descriptor) {
                    continue;
                }

                $recipient = $this->topicToAddress((string) ($log['topics'][2] ?? ''));
                $sender = $this->topicToAddress((string) ($log['topics'][1] ?? ''));

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

    public function transaction(string $txid): ?BlockchainTransactionStatus
    {
        $receipt = $this->rpc->call('eth_getTransactionReceipt', [$txid]);

        if (! is_array($receipt) || empty($receipt)) {
            return null;
        }

        $blockNumberHex = (string) ($receipt['blockNumber'] ?? '0x0');
        $blockHash = (string) ($receipt['blockHash'] ?? '');
        $blockNumber = hexdec($this->normalizeHex($blockNumberHex));

        if ($blockNumber <= 0) {
            return null;
        }

        $head = $this->headBlock();
        $confirmations = max(0, $head - $blockNumber + 1);

        $required = (int) config('withdrawal.confirmations.default_blocks', 12);

        return new BlockchainTransactionStatus(
            txid: $txid,
            blockNumber: $blockNumber,
            blockHash: $blockHash !== '' ? $blockHash : null,
            confirmations: $confirmations,
            finalized: $confirmations >= $required
        );
    }

    public function prepareWithdrawal(
        Withdrawal $withdrawal,
        int $systemWalletId,
        array $context = []
    ): PreparedWithdrawalTransaction {
        $network = EloquentNetwork::query()->findOrFail($withdrawal->networkId());
        $pair = EloquentCurrencyNetwork::query()->findOrFail($withdrawal->currencyNetworkId());
        $wallet = EloquentSystemWallet::query()->findOrFail($systemWalletId);

        if ($wallet->network_id !== $network->id) {
            throw new DomainException('System wallet network mismatch.');
        }

        $privateKey = $this->secrets->privateKeyForSystemWalletId($systemWalletId);
        $chainId = (int) $network->chain_id;

        $nonce = (string) $this->rpc->call('eth_getTransactionCount', [
            $wallet->address,
            'pending',
        ]);

        $gasPrice = (string) $this->rpc->call('eth_gasPrice');

        $toAddress = strtolower($withdrawal->destinationAddress()->value());
        if (! str_starts_with($toAddress, '0x')) {
            $toAddress = '0x' . $toAddress;
        }

        $isToken = ! empty($pair->contract_address);

        if ($isToken) {
            $tokenContract = strtolower((string) $pair->contract_address);
            if (! str_starts_with($tokenContract, '0x')) {
                $tokenContract = '0x' . $tokenContract;
            }

            $amountBaseUnits = $this->decimalToBaseUnits($withdrawal->amount()->value(), (int) $pair->decimals);

            $data = $this->encodeErc20TransferData($toAddress, $amountBaseUnits);
            $gasLimit = $this->estimateGas(
                from: $wallet->address,
                to: $tokenContract,
                data: $data,
                value: '0x0'
            );

            $txParams = [
                'nonce' => $this->toHexQuantityFromHex($nonce),
                'gasPrice' => $this->toHexQuantityFromHex($gasPrice),
                'gas' => $this->toHexQuantity($gasLimit),
                'to' => $tokenContract,
                'value' => '0x0',
                'data' => $data,
                'chainId' => $chainId,
            ];
        } else {
            $amountWei = $this->decimalToBaseUnits($withdrawal->amount()->value(), (int) $pair->decimals);

            $txParams = [
                'nonce' => $this->toHexQuantityFromHex($nonce),
                'gasPrice' => $this->toHexQuantityFromHex($gasPrice),
                'gas' => $this->toHexQuantity(21000),
                'to' => $toAddress,
                'value' => $this->toHexQuantityFromDecimalString($amountWei),
                'data' => '0x',
                'chainId' => $chainId,
            ];
        }

        $transaction = new Transaction($txParams);
        $signedRaw = '0x' . $transaction->sign($this->normalizePrivateKey($privateKey));

        return new PreparedWithdrawalTransaction(
            fingerprint: hash('sha256', json_encode([
                'network_id' => $network->id,
                'withdrawal_id' => $withdrawal->id()->value(),
                'wallet_id' => $systemWalletId,
                'nonce' => $nonce,
                'tx_params' => $txParams,
                'context' => $context,
            ], JSON_THROW_ON_ERROR)),
            rawTransaction: $signedRaw,
            rawTransactionHash: hash('sha256', strtolower($signedRaw)),
            metadata: [
                'network_code' => $network->code,
                'wallet_address' => $wallet->address,
                'system_wallet_id' => $systemWalletId,
                'is_token' => $isToken,
                'tx_params' => $txParams,
            ]
        );
    }

    public function broadcastWithdrawalRaw(string $rawTransaction): string
    {
        $raw = $this->normalizeHex($rawTransaction);
        $txid = $this->rpc->call('eth_sendRawTransaction', ['0x' . $raw]);

        if (! is_string($txid) || $txid === '') {
            throw new DomainException('EVM broadcast returned empty txid.');
        }

        return $txid;
    }

    private function estimateGas(string $from, string $to, string $data, string $value): int
    {
        try {
            $hex = $this->rpc->call('eth_estimateGas', [[
                'from' => $from,
                'to' => $to,
                'data' => $data,
                'value' => $value,
            ]]);

            if (is_string($hex) && $hex !== '') {
                return max(21000, hexdec($this->normalizeHex($hex)));
            }
        } catch (\Throwable) {
            // fallback below
        }

        return 60000;
    }

    private function encodeErc20TransferData(string $toAddress, string $amountBaseUnits): string
    {
        $selector = 'a9059cbb';
        $addressHex = $this->normalizeAddressHex($toAddress);
        $addressWord = str_pad($addressHex, 64, '0', STR_PAD_LEFT);
        $amountWord = str_pad($this->decimalToHexQuantityFromDecimalString($amountBaseUnits), 64, '0', STR_PAD_LEFT);

        return '0x' . $selector . $addressWord . $amountWord;
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

    private function normalizePrivateKey(string $privateKey): string
    {
        return $this->normalizeHex(trim($privateKey));
    }

    private function normalizeAddress(string $value): string
    {
        $value = strtolower(trim($value));
        return str_starts_with($value, '0x') ? $value : '0x' . $value;
    }

    private function normalizeAddressHex(string $address): string
    {
        $address = strtolower(trim($address));
        $address = $this->normalizeHex($address);

        return str_pad(substr($address, -40), 40, '0', STR_PAD_LEFT);
    }
    private function topicToAddress(string $topic): string
    {
        $topic = strtolower(trim($topic));
        $topic = preg_replace('/^0x/', '', $topic);

        return '0x' . substr($topic, -40);
    }
    private function decimalToBaseUnits(string $amount, int $decimals): string
    {
        if ($decimals <= 0) {
            return bcmul($amount, '1', 0);
        }

        $factor = bcpow('10', (string) $decimals, 0);

        return bcmul($amount, $factor, 0);
    }

    private function decimalToHexQuantityFromDecimalString(string $decimal): string
    {
        $decimal = ltrim($decimal, '+');
        if ($decimal === '' || bccomp($decimal, '0', 0) <= 0) {
            return '0';
        }

        $hex = '';
        while (bccomp($decimal, '0', 0) > 0) {
            $remainder = (int) bcmod($decimal, '16');
            $hex = dechex($remainder) . $hex;
            $decimal = bcdiv($decimal, '16', 0);
        }

        return $hex === '' ? '0' : $hex;
    }

    private function hexToDecimalString(string $hex, int $decimals = 18): string
    {
        $hex = $this->normalizeHex($hex);

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

    private function toHexQuantity(int $value): string
    {
        return '0x' . dechex($value);
    }

    private function toHexQuantityFromHex(string $hex): string
    {
        return '0x' . $this->normalizeHex($hex);
    }

    private function toHexQuantityFromDecimalString(string $decimal): string
    {
        return '0x' . $this->decimalToHexQuantityFromDecimalString($decimal);
    }

    private function normalizeHex(string $hex): string
    {
        $hex = strtolower(trim($hex));

        return preg_replace('/^0x/', '', $hex) ?? $hex;
    }

}
