<?php

declare(strict_types=1);

namespace App\Infrastructure\Blockchain\Clients;

use App\Application\Deposit\DTO\DetectedBlockchainEvent;
use App\Domain\Withdrawal\Entities\Withdrawal;
use App\Infrastructure\Blockchain\Contracts\BlockchainClient;
use App\Infrastructure\Blockchain\DTO\BlockchainTransactionStatus;
use App\Infrastructure\Blockchain\DTO\PreparedWithdrawalTransaction;
use App\Infrastructure\Blockchain\Services\SystemWalletSecretResolver;
use App\Infrastructure\Blockchain\Support\JsonRpcClient;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentCurrencyNetwork;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentNetwork;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentSystemWallet;
use DomainException;

/**
 * Bitcoin Core RPC client:
 * - native UTXO scan
 * - raw tx creation/signing/broadcast
 * - tx status polling
 * Production Bitcoin client:
 *  - prepares raw transaction via Bitcoin Core RPC;
 *  - signs with signrawtransactionwithkey;
 *  - broadcasts with sendrawtransaction;
 *  - polls receipt/status via getrawtransaction/gettransaction.
 *
 *  Assumption:
 *  - Bitcoin wallet/watch-only or UTXO set is available to the node RPC.
 *  =
 *
 *  Uses listunspent, createrawtransaction, signrawtransactionwithkey, sendrawtransaction.
 *  Works well when hot wallet UTXOs are available to the RPC wallet.
 *  The WIF conversion is included, so your encrypted secret can be stored as raw hex.
 */
final class BitcoinClient implements BlockchainClient
{
    public function __construct(
        private readonly JsonRpcClient $rpc,
        private readonly int $networkId,
        private readonly SystemWalletSecretResolver $secrets,
    ) {}

    public function headBlock(): int
    {
        return (int) $this->rpc->call('getblockcount');
    }

    public function blockHash(int $blockNumber): string
    {
        return (string) $this->rpc->call('getblockhash', [$blockNumber]);
    }

    /**
     * @return array<int, DetectedBlockchainEvent>
     */
    public function scanBlock(int $blockNumber, array $tokenContracts = []): array
    {
        $events = [];
        $blockHash = $this->blockHash($blockNumber);
        $block = (array) $this->rpc->call('getblock', [$blockHash, 2]);

        foreach (($block['tx'] ?? []) as $tx) {
            $txid = (string) ($tx['txid'] ?? '');

            foreach (($tx['vout'] ?? []) as $vout) {
                $n = (int) ($vout['n'] ?? 0);
                $value = (string) ($vout['value'] ?? '0');

                $addresses = data_get($vout, 'scriptPubKey.addresses', []);
                if (! is_array($addresses)) {
                    continue;
                }

                foreach ($addresses as $address) {
                    $events[] = new DetectedBlockchainEvent(
                        networkId: $this->networkId,
                        txid: $txid,
                        externalKey: $txid . ':' . $n,
                        amount: $value,
                        toAddress: (string) $address,
                        fromAddress: null,
                        blockHash: $blockHash,
                        blockNumber: $blockNumber,
                        confirmations: 1,
                        assetType: 'native',
                        contractAddress: null,
                        metadata: [
                            'source' => 'bitcoin',
                            'kind' => 'native_utxo',
                            'vout' => $n,
                        ],
                    );
                }
            }
        }

        return $events;
    }

    public function transaction(string $txid): ?BlockchainTransactionStatus
    {
        $tx = null;

        try {
            $tx = $this->rpc->call('getrawtransaction', [$txid, true]);
        } catch (\Throwable) {
            try {
                $tx = $this->rpc->call('gettransaction', [$txid]);
            } catch (\Throwable) {
                return null;
            }
        }

        if (! is_array($tx) || empty($tx)) {
            return null;
        }

        $blockHash = (string) ($tx['blockhash'] ?? $tx['blockHash'] ?? '');
        $blockNumber = null;

        if ($blockHash !== '') {
            try {
                $header = $this->rpc->call('getblockheader', [$blockHash]);
                if (is_array($header) && isset($header['height'])) {
                    $blockNumber = (int) $header['height'];
                }
            } catch (\Throwable) {
                // fallback below
            }
        }

        $confirmations = (int) ($tx['confirmations'] ?? 0);
        $required = (int) config('withdrawal.confirmations.default_blocks', 6);

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
        $wif = $this->normalizeBitcoinPrivateKey($privateKey, (bool) $network->is_testnet);

        $amountSats = $this->decimalToSats($withdrawal->amount()->value());

        $utxos = $this->rpc->call('listunspent', [1, 9999999, [$wallet->address]]);
        if (! is_array($utxos) || empty($utxos)) {
            throw new DomainException('No spendable UTXOs found for Bitcoin hot wallet.');
        }

        $selected = $this->selectUtxos($utxos, $amountSats);
        $inputTotal = array_sum(array_map(fn ($u) => (int) $u['satoshis'], $selected));

        $inputCount = count($selected);
        $outputCount = 2;
        $estimatedVBytes = $this->estimateVBytes($inputCount, $outputCount);

        $feeRateSatVbyte = $this->estimateFeeRateSatVbyte();
        $feeSats = max(1, $estimatedVBytes * $feeRateSatVbyte);

        $changeSats = $inputTotal - $amountSats - $feeSats;

        if ($changeSats < 0) {
            $selected = $this->selectUtxos($utxos, $amountSats + ($feeSats * 2));
            $inputTotal = array_sum(array_map(fn ($u) => (int) $u['satoshis'], $selected));
            $inputCount = count($selected);
            $estimatedVBytes = $this->estimateVBytes($inputCount, $outputCount);
            $feeSats = max(1, $estimatedVBytes * $feeRateSatVbyte);
            $changeSats = $inputTotal - $amountSats - $feeSats;
        }

        if ($changeSats < 0) {
            throw new DomainException('Insufficient BTC balance for amount + fee.');
        }

        $outputs = [
            $withdrawal->destinationAddress()->value() => $this->satsToBtcString($amountSats),
        ];

        if ($changeSats >= 546) {
            $outputs[$wallet->address] = $this->satsToBtcString($changeSats);
        } else {
            $feeSats += $changeSats;
            $changeSats = 0;
        }

        $inputs = array_map(static function (array $u): array {
            return [
                'txid' => $u['txid'],
                'vout' => (int) $u['vout'],
            ];
        }, $selected);

        $unsignedRaw = (string) $this->rpc->call('createrawtransaction', [$inputs, $outputs]);

        $prevtxs = array_map(static function (array $u): array {
            return [
                'txid' => $u['txid'],
                'vout' => (int) $u['vout'],
                'scriptPubKey' => (string) $u['scriptPubKey'],
                'amount' => (float) $u['amount'],
            ];
        }, $selected);

        $signed = $this->rpc->call('signrawtransactionwithkey', [
            $unsignedRaw,
            [$wif],
            $prevtxs,
        ]);

        $signedHex = (string) data_get($signed, 'hex', '');

        if ($signedHex === '') {
            throw new DomainException('Unable to sign Bitcoin withdrawal transaction.');
        }

        return new PreparedWithdrawalTransaction(
            fingerprint: hash('sha256', json_encode([
                'network_id' => $network->id,
                'withdrawal_id' => $withdrawal->id()->value(),
                'wallet_id' => $systemWalletId,
                'inputs' => $inputs,
                'outputs' => $outputs,
                'fee_sats' => $feeSats,
                'context' => $context,
            ], JSON_THROW_ON_ERROR)),
            rawTransaction: $signedHex,
            rawTransactionHash: hash('sha256', strtolower($signedHex)),
            metadata: [
                'network_code' => $network->code,
                'wallet_address' => $wallet->address,
                'system_wallet_id' => $systemWalletId,
                'inputs' => $inputs,
                'outputs' => $outputs,
                'fee_sats' => $feeSats,
                'change_sats' => $changeSats,
            ]
        );
    }

    public function broadcastWithdrawalRaw(string $rawTransaction): string
    {
        $txid = $this->rpc->call('sendrawtransaction', [$rawTransaction]);

        if (! is_string($txid) || $txid === '') {
            throw new DomainException('Bitcoin broadcast returned empty txid.');
        }

        return $txid;
    }

    private function estimateFeeRateSatVbyte(): int
    {
        try {
            $fee = $this->rpc->call('estimatesmartfee', [2]);
            $feerateBtcPerKb = (float) data_get($fee, 'feerate', 0.0001);
            $satPerVbyte = (int) max(1, round(($feerateBtcPerKb * 100000000) / 1000));

            return $satPerVbyte > 0 ? $satPerVbyte : 10;
        } catch (\Throwable) {
            return 10;
        }
    }

    private function estimateVBytes(int $inputs, int $outputs): int
    {
        return (int) (10 + ($inputs * 148) + ($outputs * 34));
    }

    private function selectUtxos(array $utxos, int $targetSats): array
    {
        usort($utxos, static fn ($a, $b) => ((int) (($a['amount'] ?? 0) * 100000000)) <=> ((int) (($b['amount'] ?? 0) * 100000000)));

        $selected = [];
        $sum = 0;

        foreach ($utxos as $utxo) {
            $sats = (int) round(((float) $utxo['amount']) * 100000000);
            $utxo['satoshis'] = $sats;
            $selected[] = $utxo;
            $sum += $sats;

            if ($sum >= $targetSats) {
                break;
            }
        }

        if ($sum < $targetSats) {
            throw new DomainException('Insufficient UTXO balance.');
        }

        return $selected;
    }

    private function decimalToSats(string $amount): int
    {
        return (int) bcmul($amount, '100000000', 0);
    }

    private function satsToBtcString(int $sats): string
    {
        return number_format($sats / 100000000, 8, '.', '');
    }

    private function normalizeBitcoinPrivateKey(string $privateKey, bool $isTestnet): string
    {
        $privateKey = trim($privateKey);

        if (preg_match('/^[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{50,52}$/', $privateKey)) {
            return $privateKey;
        }

        $hex = preg_replace('/^0x/', '', strtolower($privateKey)) ?? strtolower($privateKey);
        if (! preg_match('/^[0-9a-f]{64}$/', $hex)) {
            throw new DomainException('Bitcoin private key must be WIF or 32-byte hex.');
        }

        $prefix = $isTestnet ? 'ef' : '80';
        $payload = hex2bin($prefix . $hex . '01');

        if ($payload === false) {
            throw new DomainException('Unable to convert Bitcoin private key to WIF.');
        }

        return $this->base58CheckEncode($payload);
    }

    private function base58CheckEncode(string $data): string
    {
        $checksum = substr(hash('sha256', hash('sha256', $data, true), true), 0, 4);
        $binary = $data . $checksum;

        return $this->base58Encode($binary);
    }

    private function base58Encode(string $binary): string
    {
        $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
        $num = '0';

        foreach (unpack('C*', $binary) as $byte) {
            $num = bcadd(bcmul($num, '256', 0), (string) $byte, 0);
        }

        $encoded = '';
        while (bccomp($num, '0', 0) > 0) {
            $rem = (int) bcmod($num, '58');
            $encoded = $alphabet[$rem] . $encoded;
            $num = bcdiv($num, '58', 0);
        }

        foreach (str_split($binary) as $char) {
            if ($char === "\x00") {
                $encoded = '1' . $encoded;
            } else {
                break;
            }
        }

        return $encoded;
    }
}
