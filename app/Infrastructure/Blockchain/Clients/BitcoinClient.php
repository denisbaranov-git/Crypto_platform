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
        // получаем все UTXO
        $utxos = $this->rpc->call('listunspent', [1, 9999999, [$wallet->address]]);
        if (! is_array($utxos) || empty($utxos)) {
            throw new DomainException('No spendable UTXOs found for Bitcoin hot wallet.');
        }
        // получаем сумые большие UXTO к сумме вывода, чтобы комиссия была меньше
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

//                             //inspect and TO DO !!!!
///**
// * алгоритм Coin Control  Cтратегия выбора одного крупного UTXO вместо сбора мелочи(c последующей платы большого fee).
// */
//
//php
//<?php
//
//class BitcoinSmartWallet {
//    private $rpcUrl;
//    private $rpcUser;
//    private $rpcPassword;
//
//    public function __construct($url, $user, $password) {
//        $this->rpcUrl = $url;
//        $this->rpcUser = $user;
//        $this->rpcPassword = $password;
//    }
//
//    /**
//     * Базовый вызов RPC  //denis  replace to JsonRpcClient !!!!!!!!
//     */
//    private function callRPC($method, $params = []) {
//        $ch = curl_init();
//        curl_setopt($ch, CURLOPT_URL, $this->rpcUrl);
//        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//        curl_setopt($ch, CURLOPT_USERPWD, $this->rpcUser . ":" . $this->rpcPassword);
//        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
//        curl_setopt($ch, CURLOPT_POST, true);
//
//        $payload = json_encode([
//            'jsonrpc' => '1.0',
//            'id' => time(),
//            'method' => $method,
//            'params' => $params
//        ]);
//
//        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
//
//        $response = curl_exec($ch);
//        curl_close($ch);
//
//        $decoded = json_decode($response, true);
//        if (isset($decoded['error'])) {
//            throw new Exception("RPC Error: " . $decoded['error']['message']);
//        }
//
//        return $decoded['result'];
//    }
//
//    /**
//     * Получаем список ВСЕХ непотраченных выходов (UTXO)
//     */
//    private function listUnspent() {
//        return $this->callRPC('listunspent', [0, 9999999]);
//    }
//
//    /**
//     * "Умный" алгоритм выбора UTXO (Coin Selection)
//     *
//     * Стратегия: Найти ОДИН крупный вход, который покрывает сумму + комиссию + запас
//     * Это предотвращает раздувание транзакции пылью
//     */
//    private function selectBestUtxo($targetAmount, $feeRatePerKb = 10000) {
//        $utxos = $this->listUnspent();
//
//        if (empty($utxos)) {
//            throw new Exception("Нет доступных UTXO");
//        }
//
//        // Сортируем от большего к меньшему (чтобы брать крупные купюры)
//        usort($utxos, function($a, $b) {
//            return $b['amount'] <=> $a['amount'];
//        });
//
//        $selected = null;
//
//        // Сценарий 1: Ищем идеальный один UTXO (Best Practice)
//        // Ищем монету, которая: Больше чем (сумма + мин.комиссия)
//        // Но не слишком большую, чтобы не создавать гигантскую сдачу
//        foreach ($utxos as $utxo) {
//            $estimatedFee = $this->estimateFeeForInputs([$utxo], 2, $feeRatePerKb);
//
//            if ($utxo['amount'] >= $targetAmount + $estimatedFee) {
//                // Проверяем, не слишком ли много сдачи получится (опционально)
//                // Если сдача меньше 0.0001 BTC - это пыль, можем проигнорировать
//                $change = $utxo['amount'] - $targetAmount - $estimatedFee;
//                if ($change > 0.00000500) { // Сдача должна быть > 500 сатоши
//                    $selected = $utxo;
//                    break;
//                }
//            }
//        }
//
//        // Сценарий 2: Если одной монеты нет - берем САМУЮ КРУПНУЮ и добавляем к ней минимально
//        // необходимых мелких (Жадный алгоритм)
//        if (!$selected) {
//            $totalSelected = 0;
//            $selectedUtxos = [];
//
//            foreach ($utxos as $utxo) {
//                $selectedUtxos[] = $utxo;
//                $totalSelected += $utxo['amount'];
//
//                $estimatedFee = $this->estimateFeeForInputs($selectedUtxos, 2, $feeRatePerKb);
//
//                if ($totalSelected >= $targetAmount + $estimatedFee) {
//                    $selected = $selectedUtxos;
//                    break;
//                }
//            }
//        }
//
//        if (!$selected) {
//            throw new Exception("Недостаточно средств для покрытия суммы " . $targetAmount);
//        }
//
//        return is_array($selected) && isset($selected['txid']) ? [$selected] : $selected;
//    }
//
//    /**
//     * Оценка комиссии для заданного набора входов
//     * Формула: (Inputs * 68 + Outputs * 31 + 10) * FeeRate / 1000
//     */
//    private function estimateFeeForInputs($inputs, $outputsCount, $feeRatePerKb) {
//        $inputsCount = is_array($inputs[0] ?? null) ? count($inputs) : 1;
//        $txSizeVBytes = ($inputsCount * 68) + ($outputsCount * 31) + 10;
//        // Комиссия в BTC = (размер в vBytes) * (sat/vB) / 100_000_000
//        return ($txSizeVBytes * $feeRatePerKb) / 100000000;
//    }
//
//    /**
//     * Создание "умной" транзакции с явным указанием UTXO
//     * Это и есть Coin Control в действии
//     */
//    public function sendWithCoinControl($toAddress, $amount) {
//        // 1. Получаем текущую ставку комиссии из сети
//        $feeRate = $this->getSmartFeeRate();
//
//        // 2. Выбираем входы по нашей "умной" стратегии
//        $selectedInputs = $this->selectBestUtxo($amount, $feeRate);
//
//        echo "[DEBUG] Выбрано входов: " . count($selectedInputs) . "\n";
//        foreach ($selectedInputs as $input) {
//            echo "[DEBUG] - UTXO: " . $input['txid'] . ":" . $input['vout'] .
//                " Сумма: " . $input['amount'] . " BTC\n";
//        }
//
//        // 3. Формируем сырую транзакцию (Создаем черновик)
//        $inputsForRpc = array_map(function($utxo) {
//            return [
//                'txid' => $utxo['txid'],
//                'vout' => $utxo['vout']
//            ];
//        }, $selectedInputs);
//
//        $outputs = [
//            $toAddress => $amount
//        ];
//
//        // ВАЖНО: Используем createrawtransaction, НЕ fundrawtransaction
//        // fundrawtransaction может добавить лишние входы и сломать нашу стратегию
//        $rawTx = $this->callRPC('createrawtransaction', [$inputsForRpc, $outputs]);
//
//        // 4. Финальный расчет комиссии и добавление адреса сдачи
//        // Получаем новый адрес для сдачи (лучше каждый раз новый для приватности)
//        $changeAddress = $this->callRPC('getrawchangeaddress');
//
//        // Рассчитываем точную комиссию для этого конкретного набора входов
//        $txHex = $rawTx;
//        $decoded = $this->callRPC('decoderawtransaction', [$txHex]);
//        $vBytes = $decoded['vsize'];
//        $exactFee = ($vBytes * $feeRate) / 100000000; // в BTC
//
//        $totalInput = array_sum(array_column($selectedInputs, 'amount'));
//        $change = $totalInput - $amount - $exactFee;
//
//        // Пересоздаем транзакцию с учетом сдачи
//        $outputsWithChange = [
//            $toAddress => $amount,
//            $changeAddress => round($change, 8) // округляем до сатоши
//        ];
//
//        $finalRawTx = $this->callRPC('createrawtransaction', [$inputsForRpc, $outputsWithChange]);
//
//        echo "[INFO] Сумма перевода: {$amount} BTC\n";
//        echo "[INFO] Сдача: {$change} BTC на адрес {$changeAddress}\n";
//        echo "[INFO] Комиссия сети: {$exactFee} BTC (~" . ($exactFee * 100000000) . " sat)\n";
//        echo "[INFO] Задействовано входов: " . count($selectedInputs) . " (Размер транзакции: {$vBytes} vBytes)\n";
//
//        // 5. Подписываем транзакцию приватными ключами кошелька
//        $signedTx = $this->callRPC('signrawtransactionwithwallet', [$finalRawTx]);
//
//        if (!$signedTx['complete']) {
//            throw new Exception("Не удалось подписать транзакцию: " . json_encode($signedTx['errors']));
//        }
//
//        // 6. Отправка в сеть (ТОЛЬКО ПОСЛЕ ПРОВЕРКИ В БУХГАЛТЕРИИ!)
//        // Закомментировано для безопасности примера
//        // $txId = $this->callRPC('sendrawtransaction', [$signedTx['hex']]);
//        $txId = "debug_mode_no_broadcast_" . md5($signedTx['hex']);
//
//        return [
//            'txid' => $txId,
//            'amount' => $amount,
//            'fee' => $exactFee,
//            'inputs_used' => count($selectedInputs),
//            'raw_hex' => $signedTx['hex'] // Для аудита
//        ];
//    }
//
//    /**
//     * Получение оптимальной ставки комиссии из сети
//     */
//    private function getSmartFeeRate() {
//        try {
//            $estimates = $this->callRPC('estimatesmartfee', [6]); // 6 блоков ~ 1 час
//            if (isset($estimates['feerate'])) {
//                // feerate возвращается в BTC/kB, переводим в sat/vByte
//                return ($estimates['feerate'] * 100000000) / 1000;
//            }
//        } catch (Exception $e) {
//            // Fallback
//        }
//        return 10000; // 10 sat/vB - стандартный безопасный минимум
//    }
//
//    /**
//     * Отдельный метод для КОНСОЛИДАЦИИ (сбор пыли)
//     * Это НЕ должно вызываться при выводе клиенту!
//     */
//    public function consolidateDust($minAmount = 0.001) {
//        $utxos = $this->listUnspent();
//        $dust = array_filter($utxos, function($utxo) use ($minAmount) {
//            return $utxo['amount'] < $minAmount && $utxo['confirmations'] > 10;
//        });
//
//        if (count($dust) < 10) {
//            return "Недостаточно пыли для консолидации";
//        }
//
//        echo "Запуск консолидации " . count($dust) . " пыльных UTXO в фоне...\n";
//        // ... код консолидации (отправка всех пыльных входов на 1 адрес кошелька) ...
//    }
//}
//
//// ========== ИСПОЛЬЗОВАНИЕ В ПРОДАКШЕНЕ ==========
//
//$wallet = new BitcoinSmartWallet(
//    'http://127.0.0.1:8332',
//    'rpcuser',
//    'rpcpassword'
//);
//
//try {
//    // Пример: Вывод 0.3 BTC клиенту
//    $result = $wallet->sendWithCoinControl('bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh', 0.3);
//
//    echo "\n=== РЕЗУЛЬТАТ ОПЕРАЦИИ ===\n";
//    echo "TXID: " . $result['txid'] . "\n";
//    echo "Сумма клиенту: " . $result['amount'] . " BTC\n";
//    echo "Расход на газ: " . $result['fee'] . " BTC\n";
//    echo "Использовано входов: " . $result['inputs_used'] . "\n";
//
//    // Здесь должна быть запись в Ledger:
//    // Debit: Expense (Network Fee) - $result['fee']
//    // Debit: User Liability - $result['amount']
//    // Credit: Hot Wallet Balance - ($result['amount'] + $result['fee'])
//
//} catch (Exception $e) {
//    echo "Ошибка: " . $e->getMessage() . "\n";
//}
///**
// * Ключевые моменты этого кода для продакшена:
// * selectBestUtxo() — Сердце алгоритма. Он ищет одну большую купюру, а не собирает мелочь. Это напрямую экономит деньги платформы на комиссиях сети.
// *
// * createrawtransaction вместо fundrawtransaction
// * Почему это важно: fundrawtransaction (автоматический режим) сам добавит 1000 мелких входов, если крупных не хватит. Мы явно указываем входы, реализуя Coin Control.
// *
// * Расчет комиссии ДО отправки
// * В реальном коде обязательно нужно проверить, что $exactFee не превышает лимиты (например, не более 5% от суммы перевода), иначе можно случайно отдать $100 комиссии при переводе $10.
// *
// * Разделение консолидации и вывода
// * Метод consolidateDust() — это отдельный cron-job на ночь. Он никогда не вызывается во время обработки запроса пользователя, чтобы пользователь не ждал 10 минут сборки огромной транзакции.
// *
// * Безопасность
// * В примере вызов sendrawtransaction закомментирован. В реальном коде нужно сперва записать намерение в БД, убедиться, что бухгалтерская проводка зафиксирована,
// * и только потом отправлять транзакцию в сеть. Иначе при падении PHP после отправки в сеть, но до записи в БД, вы потеряете след денег.
// */
