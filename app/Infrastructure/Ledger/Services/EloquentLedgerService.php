<?php

declare(strict_types=1);

namespace App\Infrastructure\Ledger\Services;

use App\Domain\Ledger\Contracts\LedgerService;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentAccount;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentAccountTransaction;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentDeposit;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentLedgerOperation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use DomainException;

final class EloquentLedgerService implements LedgerService
{
    public function postDepositCredit(
        int $depositId,
        string $operationId,
        array $metadata = []
    ): void {
        DB::transaction(function () use ($depositId, $operationId, $metadata): void {
            /**
             * 1) Лочим deposit.
             * Нельзя допустить двойной credit.
             */
            $deposit = EloquentDeposit::query()
                ->whereKey($depositId)
                ->lockForUpdate()
                ->firstOrFail();

            /**
             * 2) Если депозит уже зачислен — выходим.
             */
            if ($deposit->status === 'credited') {
                return;
            }

            /**
             * 3) Если депозит уже стал reorged/reversed — зачислять нельзя.
             */
            if (in_array($deposit->status, ['reorged', 'reversed'], true)) {
                throw new DomainException('Deposit cannot be credited because it is already reorged/reversed.');
            }

            if ($deposit->status !== 'confirmed') {
                throw new DomainException('Deposit must be confirmed before crediting.');
            }

            /**
             * 4) Идемпотентная операция.
             * Один deposit-credit should exist once.
             */
            $operation = $this->findOrCreateOperation(
                idempotencyKey: $operationId,
                type: 'deposit_credit',
                referenceType: 'deposit',
                referenceId: $depositId,
                metadata: $metadata
            );

            if ($operation->status === 'posted') {
                return;
            }

            /**
             * 5) Лочим account.
             * account = текущий state баланса.
             */
            $account = EloquentAccount::query()
                ->where('user_id', $deposit->user_id)
                ->where('currency_network_id', $deposit->currency_network_id)
                ->lockForUpdate()
                ->first();

            if (! $account) {
                try {
                    $account = EloquentAccount::create([
                        'user_id' => $deposit->user_id,
                        'currency_network_id' => $deposit->currency_network_id,
                        'balance' => '0',
                        'reserved_balance' => '0',
                        'status' => 'active',
                        'version' => 0,
                        'metadata' => [],
                    ]);
                } catch (QueryException $e) {
                    /**
                     * Если два процесса одновременно создали account,
                     * unique constraint спасает от дубля.
                     */
                    $account = EloquentAccount::query()
                        ->where('user_id', $deposit->user_id)
                        ->where('currency_network_id', $deposit->currency_network_id)
                        ->lockForUpdate()
                        ->firstOrFail();
                }

                $account->refresh();
            }

            $before = (string) $account->balance;
            $after = bcadd($before, (string) $deposit->amount, 18);

            /**
             * 6) Обновляем current state.
             */
            $account->balance = $after;
            $account->version = ((int) $account->version) + 1;
            $account->save();

            /**
             * 7) Journal entry.
             */
            EloquentAccountTransaction::create([
                'ledger_operation_id' => $operation->id,
                'account_id' => $account->id,
                'currency_network_id' => $deposit->currency_network_id,
                'direction' => 'credit',
                'amount' => (string) $deposit->amount,
                'balance_before' => $before,
                'balance_after' => $after,
                'reference_type' => 'deposit',
                'reference_id' => $deposit->id,
                'status' => 'confirmed',
                'metadata' => $metadata,
            ]);

            /**
             * 8) Operation posted.
             */
            $operation->status = 'posted';
            $operation->posted_at = now();
            $operation->save();

            /**
             * 9) Deposit becomes credited.
             */
            $deposit->status = 'credited';
            $deposit->credited_at = now();
            $deposit->credited_operation_id = $operation->id;
            $deposit->save();
        });
    }

    public function reverseDepositCredit(
        int $depositId,
        array $metadata = []
    ): void {
        DB::transaction(function () use ($depositId, $metadata): void {
            /**
             * 1) Лочим deposit.
             */
            $deposit = EloquentDeposit::query()
                ->whereKey($depositId)
                ->lockForUpdate()
                ->firstOrFail();

            /**
             * 2) Для reorg-flow reversal нужен только там, где реально был credit.
             * Если депозит никогда не был credited — ledger reverse не нужен.
             */
            if ($deposit->status === 'reorged' && empty($deposit->credited_operation_id)) {
                return;
            }

            if (! in_array($deposit->status, ['credited', 'reorged'], true)) {
                throw new DomainException('Only credited or reorged deposits can be reversed.');
            }

            if (! empty($deposit->reversal_operation_id)) {
                return;
            }

            if (empty($deposit->credited_operation_id)) {
                throw new DomainException('Cannot reverse deposit without credited_operation_id.');
            }

            /**
             * 3) Идемпотентный reverse key.
             * Одна reversal-операция на один deposit.
             */
            $reverseOperationId = 'deposit-reversal:' . $deposit->id;

            $operation = $this->findOrCreateOperation(
                idempotencyKey: $reverseOperationId,
                type: 'deposit_reversal',
                referenceType: 'deposit',
                referenceId: $deposit->id,
                metadata: $metadata
            );

            if ($operation->status === 'posted') {
                return;
            }

            /**
             * 4) Лочим account.
             */
            $account = EloquentAccount::query()
                ->where('user_id', $deposit->user_id)
                ->where('currency_network_id', $deposit->currency_network_id)
                ->lockForUpdate()
                ->firstOrFail();

            /**
             * 5) Проверяем доступный баланс.
             * Если пользователь уже потратил деньги, reversal может стать невозможным.
             */
            $available = bcsub((string) $account->balance, (string) $account->reserved_balance, 18);

            if (bccomp($available, (string) $deposit->amount, 18) < 0) {
                $deposit->status = 'reversal_failed';
                $deposit->reversal_failed_at = now();
                $deposit->reversal_reason = 'insufficient_available_balance';
                $deposit->save();

                $operation->status = 'failed';
                $operation->failed_at = now();
                $operation->metadata = array_merge($operation->metadata ?? [], [
                    'reason' => 'insufficient_available_balance',
                ]);
                $operation->save();

                throw new DomainException('Insufficient available balance for deposit reversal.');
            }

            $before = (string) $account->balance;
            $after = bcsub($before, (string) $deposit->amount, 18);

            /**
             * 6) Обновляем current state.
             */
            $account->balance = $after;
            $account->version = ((int) $account->version) + 1;
            $account->save();

            /**
             * 7) Journal entry.
             */
            EloquentAccountTransaction::create([
                'ledger_operation_id' => $operation->id,
                'account_id' => $account->id,
                'currency_network_id' => $deposit->currency_network_id,
                'direction' => 'debit',
                'amount' => (string) $deposit->amount,
                'balance_before' => $before,
                'balance_after' => $after,
                'reference_type' => 'deposit',
                'reference_id' => $deposit->id,
                'status' => 'confirmed',
                'metadata' => array_merge($metadata, [
                    'reversal_of_operation_id' => $deposit->credited_operation_id,
                    'reason' => 'blockchain_reorg',
                ]),
            ]);

            /**
             * 8) Operation posted.
             */
            $operation->status = 'posted';
            $operation->posted_at = now();
            $operation->save();

            /**
             * 9) Deposit becomes reversed.
             */
            $deposit->status = 'reversed';
            $deposit->reversed_at = now();
            $deposit->reversal_operation_id = $operation->id;
            $deposit->save();
        });
    }

    private function findOrCreateOperation(
        string $idempotencyKey,
        string $type,
        ?string $referenceType,
        ?int $referenceId,
        array $metadata = []
    ): EloquentLedgerOperation {
        $operation = EloquentLedgerOperation::query()
            ->where('idempotency_key', $idempotencyKey)
            ->lockForUpdate()
            ->first();

        if ($operation) {
            return $operation;
        }

        try {
            return EloquentLedgerOperation::create([
                'id' => (string) Str::uuid(),
                'idempotency_key' => $idempotencyKey,
                'type' => $type,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'status' => 'pending',
                'metadata' => $metadata,
            ]);
        } catch (QueryException $e) {
            $existing = EloquentLedgerOperation::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                return $existing;
            }

            throw $e;
        }
    }


//    public function reserveFunds(
//        int $userId,
//        int $currencyNetworkId,
//        string $amount,
//        string $operationId,
//        string $referenceType,
//        ?int $referenceId = null,
//        array $metadata = [],
//        ?int $expiresInSeconds = null
//    ): void {
//        DB::transaction(function () use (
//            $userId,
//            $currencyNetworkId,
//            $amount,
//            $operationId,
//            $referenceType,
//            $referenceId,
//            $metadata,
//            $expiresInSeconds
//        ): void {
//            $operation = $this->findOrCreateOperation(
//                idempotencyKey: $operationId,
//                type: 'withdrawal_reserve',
//                referenceType: $referenceType,
//                referenceId: $referenceId,
//                metadata: $metadata
//            );
//
//            if ($operation->status === 'posted') {
//                return;
//            }
//
//            $account = EloquentAccount::query()
//                ->where('user_id', $userId)
//                ->where('currency_network_id', $currencyNetworkId)
//                ->lockForUpdate()
//                ->firstOrFail();
//
//            $beforeBalance = (string) $account->balance;
//            $beforeReserved = (string) $account->reserved_balance;
//            $available = bcsub($beforeBalance, $beforeReserved, 18);
//
//            if (bccomp($available, $amount, 18) < 0) {
//                throw new DomainException('Insufficient available balance to reserve.');
//            }
//
//            $newReserved = bcadd($beforeReserved, $amount, 18);
//
//            $account->reserved_balance = $newReserved;
//            $account->version = ((int) $account->version) + 1;
//            $account->save();
//
//            $hold = EloquentLedgerHold::create([
//                'ledger_operation_id' => $operation->id,
//                'account_id' => $account->id,
//                'currency_network_id' => $currencyNetworkId,
//                'amount' => $amount,
//                'status' => 'active',
//                'reason' => 'withdrawal',
//                'expires_at' => $expiresInSeconds ? now()->addSeconds($expiresInSeconds) : null,
//                'metadata' => array_merge($metadata, [
//                    'reference_type' => $referenceType,
//                    'reference_id' => $referenceId,
//                ]),
//            ]);
//
//            $operation->status = 'posted';
//            $operation->posted_at = now();
//            $operation->save();
//        });
//    }
//
//    public function releaseFunds(
//        int $holdId,
//        string $operationId,
//        string $referenceType,
//        ?int $referenceId = null,
//        array $metadata = []
//    ): void {
//        DB::transaction(function () use ($holdId, $operationId, $referenceType, $referenceId, $metadata): void {
//            $operation = $this->findOrCreateOperation(
//                idempotencyKey: $operationId,
//                type: 'withdrawal_release',
//                referenceType: $referenceType,
//                referenceId: $referenceId,
//                metadata: $metadata
//            );
//
//            if ($operation->status === 'posted') {
//                return;
//            }
//
//            $hold = EloquentLedgerHold::query()
//                ->whereKey($holdId)
//                ->lockForUpdate()
//                ->firstOrFail();
//
//            if ($hold->status !== 'active') {
//                return;
//            }
//
//            $account = EloquentAccount::query()
//                ->whereKey($hold->account_id)
//                ->lockForUpdate()
//                ->firstOrFail();
//
//            $beforeReserved = (string) $account->reserved_balance;
//            $newReserved = bcsub($beforeReserved, (string) $hold->amount, 18);
//
//            $account->reserved_balance = $newReserved;
//            $account->version = ((int) $account->version) + 1;
//            $account->save();
//
//            $hold->status = 'released';
//            $hold->released_at = now();
//            $hold->save();
//
//            $operation->status = 'posted';
//            $operation->posted_at = now();
//            $operation->save();
//        });
//    }
//
//    public function consumeHold(
//        int $holdId,
//        string $operationId,
//        string $referenceType,
//        ?int $referenceId = null,
//        array $metadata = []
//    ): void {
//        DB::transaction(function () use ($holdId, $operationId, $referenceType, $referenceId, $metadata): void {
//            $operation = $this->findOrCreateOperation(
//                idempotencyKey: $operationId,
//                type: 'withdrawal_consume',
//                referenceType: $referenceType,
//                referenceId: $referenceId,
//                metadata: $metadata
//            );
//
//            if ($operation->status === 'posted') {
//                return;
//            }
//
//            $hold = EloquentLedgerHold::query()
//                ->whereKey($holdId)
//                ->lockForUpdate()
//                ->firstOrFail();
//
//            if ($hold->status !== 'active') {
//                throw new DomainException('Only active hold can be consumed.');
//            }
//
//            $account = EloquentAccount::query()
//                ->whereKey($hold->account_id)
//                ->lockForUpdate()
//                ->firstOrFail();
//
//            $beforeBalance = (string) $account->balance;
//            $beforeReserved = (string) $account->reserved_balance;
//
//            if (bccomp($beforeReserved, (string) $hold->amount, 18) < 0) {
//                throw new DomainException('Reserved balance is less than hold amount.');
//            }
//
//            if (bccomp($beforeBalance, (string) $hold->amount, 18) < 0) {
//                throw new DomainException('Insufficient balance for hold consumption.');
//            }
//
//            $account->reserved_balance = bcsub($beforeReserved, (string) $hold->amount, 18);
//            $account->balance = bcsub($beforeBalance, (string) $hold->amount, 18);
//            $account->version = ((int) $account->version) + 1;
//            $account->save();
//
//            EloquentAccountTransaction::create([
//                'ledger_operation_id' => $operation->id,
//                'account_id' => $account->id,
//                'currency_network_id' => $hold->currency_network_id,
//                'direction' => 'debit',
//                'amount' => $hold->amount,
//                'balance_before' => $beforeBalance,
//                'balance_after' => (string) $account->balance,
//                'reference_type' => $referenceType,
//                'reference_id' => $referenceId,
//                'status' => 'confirmed',
//                'metadata' => array_merge($metadata, [
//                    'hold_id' => $hold->id,
//                    'consumed_from_hold' => true,
//                ]),
//            ]);
//
//            $hold->status = 'consumed';
//            $hold->consumed_at = now();
//            $hold->save();
//
//            $operation->status = 'posted';
//            $operation->posted_at = now();
//            $operation->save();
//        });
//    }
//
//    public function transferInternal(
//        int $fromUserId,
//        int $toUserId,
//        int $currencyNetworkId,
//        string $amount,
//        string $operationId,
//        string $referenceType,
//        ?int $referenceId = null,
//        array $metadata = []
//    ): void {
//        DB::transaction(function () use (
//            $fromUserId,
//            $toUserId,
//            $currencyNetworkId,
//            $amount,
//            $operationId,
//            $referenceType,
//            $referenceId,
//            $metadata
//        ): void {
//            if ($fromUserId === $toUserId) {
//                throw new DomainException('Self transfer is not allowed.');
//            }
//
//            $operation = $this->findOrCreateOperation(
//                idempotencyKey: $operationId,
//                type: 'internal_transfer',
//                referenceType: $referenceType,
//                referenceId: $referenceId,
//                metadata: $metadata
//            );
//
//            if ($operation->status === 'posted') {
//                return;
//            }
//
//            /**
//             * Важно: читаем оба аккаунта под lock в детерминированном порядке.
//             */
//            $sender = EloquentAccount::query()
//                ->where('user_id', $fromUserId)
//                ->where('currency_network_id', $currencyNetworkId)
//                ->lockForUpdate()
//                ->firstOrFail();
//
//            $receiver = EloquentAccount::query()
//                ->where('user_id', $toUserId)
//                ->where('currency_network_id', $currencyNetworkId)
//                ->lockForUpdate()
//                ->first();
//
//            if (! $receiver) {
//                $receiver = EloquentAccount::create([
//                    'user_id' => $toUserId,
//                    'currency_network_id' => $currencyNetworkId,
//                    'balance' => '0',
//                    'reserved_balance' => '0',
//                    'status' => 'active',
//                    'version' => 0,
//                    'metadata' => [],
//                ]);
//
//                $receiver->refresh();
//            }
//
//            $senderBefore = (string) $sender->balance;
//            $receiverBefore = (string) $receiver->balance;
//
//            if (bccomp($senderBefore, $amount, 18) < 0) {
//                throw new DomainException('Insufficient funds for internal transfer.');
//            }
//
//            $senderAfter = bcsub($senderBefore, $amount, 18);
//            $receiverAfter = bcadd($receiverBefore, $amount, 18);
//
//            $sender->balance = $senderAfter;
//            $sender->version = ((int) $sender->version) + 1;
//            $sender->save();
//
//            $receiver->balance = $receiverAfter;
//            $receiver->version = ((int) $receiver->version) + 1;
//            $receiver->save();
//
//            EloquentAccountTransaction::insert([
//                [
//                    'ledger_operation_id' => $operation->id,
//                    'account_id' => $sender->id,
//                    'currency_network_id' => $currencyNetworkId,
//                    'direction' => 'debit',
//                    'amount' => $amount,
//                    'balance_before' => $senderBefore,
//                    'balance_after' => $senderAfter,
//                    'reference_type' => $referenceType,
//                    'reference_id' => $referenceId,
//                    'status' => 'confirmed',
//                    'metadata' => array_merge($metadata, ['side' => 'sender']),
//                    'created_at' => now(),
//                    'updated_at' => now(),
//                ],
//                [
//                    'ledger_operation_id' => $operation->id,
//                    'account_id' => $receiver->id,
//                    'currency_network_id' => $currencyNetworkId,
//                    'direction' => 'credit',
//                    'amount' => $amount,
//                    'balance_before' => $receiverBefore,
//                    'balance_after' => $receiverAfter,
//                    'reference_type' => $referenceType,
//                    'reference_id' => $referenceId,
//                    'status' => 'confirmed',
//                    'metadata' => array_merge($metadata, ['side' => 'receiver']),
//                    'created_at' => now(),
//                    'updated_at' => now(),
//                ],
//            ]);
//
//            $operation->status = 'posted';
//            $operation->posted_at = now();
//            $operation->save();
//        });
//    }
}
