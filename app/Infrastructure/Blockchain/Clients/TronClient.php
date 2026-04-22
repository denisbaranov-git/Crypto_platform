<?php

declare(strict_types=1);

namespace App\Infrastructure\Blockchain\Clients;

use App\Application\Deposit\DTO\DetectedBlockchainEvent;
use App\Domain\Deposit\DTO\TokenContractDescriptor;
use App\Domain\Withdrawal\Entities\Withdrawal;
use App\Infrastructure\Blockchain\Contracts\BlockchainClient;
use App\Infrastructure\Blockchain\DTO\BlockchainTransactionStatus;
use App\Infrastructure\Blockchain\DTO\PreparedWithdrawalTransaction;
use App\Infrastructure\Blockchain\Services\SystemWalletSecretResolver;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentCurrencyNetwork;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentNetwork;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentSystemWallet;
use DomainException;
use Illuminate\Support\Facades\Http;

/**
 * Tron:
 * - native TRX
 * - TRC20
 * - create/sign/broadcast via fullnode HTTP RPC
 */
final class TronClient implements BlockchainClient
{
    public function __construct(
        private readonly string $rpcUrl,
        private readonly int $networkId,
        private readonly SystemWalletSecretResolver $secrets,
    ) {}

    public function headBlock(): int
    {
        $response = $this->post('/wallet/getnowblock', []);

        return (int) data_get($response, 'block_header.raw_data.number', 0);
    }

    public function blockHash(int $blockNumber): string
    {
        $block = $this->post('/wallet/getblockbynum', ['num' => $blockNumber]);

        return (string) data_get($block, 'blockID', '');
    }

    /**
     * @return array<int, DetectedBlockchainEvent>
     */
    public function scanBlock(int $blockNumber, array $tokenContracts = []): array
    {
        $events = [];
        $block = $this->post('/wallet/getblockbynum', ['num' => $blockNumber]);
        $blockHash = (string) data_get($block, 'blockID', '');
        $transactions = data_get($block, 'transactions', []);

        if (! is_array($transactions)) {
            return [];
        }

        foreach ($transactions as $tx) {
            $txid = (string) data_get($tx, 'txID', '');
            $contractType = (string) data_get($tx, 'raw_data.contract.0.type', '');
            $value = data_get($tx, 'raw_data.contract.0.parameter.value', []);

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

            if ($contractType === 'TriggerSmartContract') {
                $receipt = $this->post('/wallet/gettransactioninfobyid', ['value' => $txid]);
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

    public function transaction(string $txid): ?BlockchainTransactionStatus
    {
        $receipt = $this->post('/wallet/gettransactioninfobyid', ['value' => $txid]);

        if (! is_array($receipt) || empty($receipt)) {
            return null;
        }

        $blockNumber = (int) data_get($receipt, 'blockNumber', 0);
        $blockHash = (string) data_get($receipt, 'blockHash', '');

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

        $isToken = ! empty($pair->contract_address);

        if ($isToken) {
            $tx = $this->post('/wallet/triggersmartcontract', [
                'owner_address' => $this->normalizeTronAddress($wallet->address),
                'contract_address' => $this->normalizeTronAddress((string) $pair->contract_address),
                'function_selector' => 'transfer(address,uint256)',
                'parameter' => $this->encodeTrc20TransferParameter(
                    destinationAddress: $withdrawal->destinationAddress()->value(),
                    amount: $this->decimalToBaseUnits($withdrawal->amount()->value(), (int) $pair->decimals)
                ),
                'fee_limit' => $this->tokenFeeLimitSun(),
                'call_value' => 0,
                'visible' => false,
            ]);
        } else {
            $tx = $this->post('/wallet/createtransaction', [
                'to_address' => $this->normalizeTronAddress($withdrawal->destinationAddress()->value()),
                'owner_address' => $this->normalizeTronAddress($wallet->address),
                'amount' => $this->decimalToBaseUnits($withdrawal->amount()->value(), (int) $pair->decimals),
                'visible' => false,
            ]);
        }

        $unsignedTx = data_get($tx, 'transaction');

        if (! is_array($unsignedTx) || empty($unsignedTx)) {
            throw new DomainException('Unable to create Tron withdrawal transaction.');
        }

        $signed = $this->post('/wallet/gettransactionsign', [
            'transaction' => $unsignedTx,
            'privateKey' => $this->normalizePrivateKey($privateKey),
            'visible' => false,
        ]);

        $signedTx = data_get($signed, 'transaction', $signed);

        if (! is_array($signedTx) || empty($signedTx)) {
            throw new DomainException('Unable to sign Tron withdrawal transaction.');
        }

        $signedJson = json_encode($signedTx, JSON_THROW_ON_ERROR);
        $txid = (string) data_get($signedTx, 'txID', '');

        return new PreparedWithdrawalTransaction(
            fingerprint: hash('sha256', json_encode([
                'network_id' => $network->id,
                'withdrawal_id' => $withdrawal->id()->value(),
                'wallet_id' => $systemWalletId,
                'unsigned_tx' => $unsignedTx,
                'context' => $context,
            ], JSON_THROW_ON_ERROR)),
            rawTransaction: $signedJson,
            rawTransactionHash: hash('sha256', $signedJson),
            metadata: [
                'network_code' => $network->code,
                'wallet_address' => $wallet->address,
                'system_wallet_id' => $systemWalletId,
                'is_token' => $isToken,
                'txid' => $txid !== '' ? $txid : null,
                'unsigned_tx' => $unsignedTx,
            ]
        );
    }

    public function broadcastWithdrawalRaw(string $rawTransaction): string
    {
        $tx = json_decode($rawTransaction, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($tx)) {
            throw new DomainException('Invalid Tron transaction payload.');
        }

        $response = $this->post('/wallet/broadcasttransaction', $tx);

        $result = (bool) data_get($response, 'result', false);
        if (! $result) {
            $message = (string) data_get($response, 'message', 'Tron broadcast failed.');
            throw new DomainException($message);
        }

        $txid = (string) data_get($tx, 'txID', '');
        if ($txid === '') {
            throw new DomainException('Tron broadcast succeeded but txID is missing.');
        }

        return $txid;
    }

    private function tokenFeeLimitSun(): int
    {
        return (int) config('withdrawal.tron.token_fee_limit_sun', 15_000_000);
    }

    private function post(string $path, array $payload): array
    {
        $response = Http::timeout(30)->retry(2, 200)
            ->acceptJson()
            ->post(rtrim($this->rpcUrl, '/') . $path, $payload);

        if (! $response->successful()) {
            throw new DomainException("TRON RPC request failed: {$path} => {$response->status()}");
        }

        $json = $response->json();

        if (! is_array($json)) {
            throw new DomainException("TRON RPC invalid JSON response: {$path}");
        }

        return $json;
    }

    private function normalizePrivateKey(string $privateKey): string
    {
        return preg_replace('/^0x/', '', strtolower(trim($privateKey))) ?? strtolower(trim($privateKey));
    }

    private function normalizeTronAddress(string $address): string
    {
        $address = trim($address);

        if ($address === '') {
            throw new DomainException('Tron address cannot be empty.');
        }

        if (preg_match('/^(41)?[0-9a-fA-F]{40,42}$/', $address)) {
            $hex = strtolower($address);
            $hex = preg_replace('/^0x/', '', $hex) ?? $hex;

            if (strlen($hex) === 40) {
                return '41' . $hex;
            }

            return $hex;
        }

        $decoded = $this->base58CheckDecode($address);

        if (strlen($decoded) !== 21) {
            throw new DomainException('Invalid Tron address length.');
        }

        return bin2hex($decoded);
    }

    private function tronAddressToAbiAddress(string $address): string
    {
        $hex = $this->normalizeTronAddress($address);

        if (strlen($hex) !== 42) {
            throw new DomainException('Invalid Tron hex address.');
        }

        return substr($hex, 2);
    }

    private function encodeTrc20TransferParameter(string $destinationAddress, string $amount): string
    {
        $addr = $this->tronAddressToAbiAddress($destinationAddress);
        $amountHex = $this->decimalToHexQuantity($amount);

        return str_pad($addr, 64, '0', STR_PAD_LEFT) . str_pad($amountHex, 64, '0', STR_PAD_LEFT);
    }

    private function decimalToBaseUnits(string $amount, int $decimals): string
    {
        if ($decimals <= 0) {
            return bcmul($amount, '1', 0);
        }

        $factor = bcpow('10', (string) $decimals, 0);

        return bcmul($amount, $factor, 0);
    }

    private function decimalToHexQuantity(string $decimal): string
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

    private function hexToDecimalString(string $hex, int $decimals = 6): string
    {
        $hex = strtolower(trim($hex));
        $hex = preg_replace('/^0x/', '', $hex) ?? $hex;

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

    private function sunToTrx(string $sun): string
    {
        return bcdiv($sun, '1000000', 6);
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

    private function tronTopicToAddress(string $topic): string
    {
        $topic = strtolower(trim($topic));
        $topic = preg_replace('/^0x/', '', $topic) ?? $topic;

        return substr($topic, -34);
    }

    private function base58CheckDecode(string $input): string
    {
        $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
        $indexes = array_flip(str_split($alphabet));

        $num = '0';
        foreach (str_split($input) as $char) {
            if (! isset($indexes[$char])) {
                throw new DomainException('Invalid base58 character.');
            }

            $num = bcadd(bcmul($num, '58', 0), (string) $indexes[$char], 0);
        }

        $hex = '';
        while (bccomp($num, '0', 0) > 0) {
            $rem = (int) bcmod($num, '256');
            $hex = str_pad(dechex($rem), 2, '0', STR_PAD_LEFT) . $hex;
            $num = bcdiv($num, '256', 0);
        }

        $pad = 0;
        for ($i = 0, $l = strlen($input); $i < $l && $input[$i] === '1'; $i++) {
            $pad++;
        }

        $hex = str_repeat('00', $pad) . $hex;
        $bin = hex2bin($hex) ?: '';

        if (strlen($bin) < 4) {
            throw new DomainException('Invalid base58check payload.');
        }

        $data = substr($bin, 0, -4);
        $checksum = substr($bin, -4);
        $calc = substr(hash('sha256', hash('sha256', $data, true), true), 0, 4);

        if ($checksum !== $calc) {
            throw new DomainException('Invalid Tron base58 checksum.');
        }

        return $data;
    }
}
