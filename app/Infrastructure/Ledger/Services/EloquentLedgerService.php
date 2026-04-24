<?php

declare(strict_types=1);

namespace App\Infrastructure\Ledger\Services;

use App\Domain\Ledger\Contracts\LedgerPostingService;
use App\Domain\Ledger\Contracts\LedgerService;
use App\Domain\Ledger\Contracts\SystemAccountResolverInterface;
use App\Domain\Ledger\ValueObjects\LedgerPostingLine;
use App\Domain\Ledger\ValueObjects\LedgerDirection;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentAccount;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentDeposit;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentLedgerHold;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentLedgerOperation;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentWithdrawal;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class EloquentLedgerService implements LedgerService
{
    public function __construct(
        private readonly LedgerPostingService $posting,
        private readonly SystemAccountResolverInterface $systemAccounts,
    ) {}

    public function postDepositCredit(
        int $depositId,
        string $operationId,
        array $metadata = []
    ): void {
        DB::transaction(function () use ($depositId, $operationId, $metadata): void {
            $deposit = EloquentDeposit::query()
                ->whereKey($depositId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($deposit->status === 'credited') {
                return;
            }

            if ($deposit->status !== 'confirmed') {
                throw new DomainException('Deposit must be confirmed before crediting.');
            }

            $userAccount = EloquentAccount::query()
                ->where('owner_type', 'user')
                ->where('owner_id', $deposit->user_id)
                ->where('currency_network_id', $deposit->currency_network_id)
                ->lockForUpdate()
                ->first();

            if (! $userAccount) {
                $userAccount = EloquentAccount::create([
                    'owner_type' => 'user',
                    'owner_id' => $deposit->user_id,
                    'currency_network_id' => $deposit->currency_network_id,
                    'balance' => '0',
                    'reserved_balance' => '0',
                    'status' => 'active',
                    'version' => 0,
                    'metadata' => [],
                ]);
            }

            $clearing = $this->systemAccounts->resolveByCode('clearing', $deposit->currency_network_id);

            $this->posting->post(
                idempotencyKey: $operationId,
                type: 'deposit_credit',
                referenceType: 'deposit',
                referenceId: $depositId,
                lines: [
                    LedgerPostingLine::credit($userAccount->id, (string) $deposit->amount, ['side' => 'user']),
                    LedgerPostingLine::debit($clearing->id(), (string) $deposit->amount, ['side' => 'clearing']),
                ],
                metadata: $metadata
            );

            $deposit->status = 'credited';
            $deposit->credited_at = now();
            $deposit->credited_operation_id = $operationId;
            $deposit->save();
        });
    }

    public function reverseDepositCredit(
        int $depositId,
        array $metadata = []
    ): void {
        DB::transaction(function () use ($depositId, $metadata): void {
            $deposit = EloquentDeposit::query()
                ->whereKey($depositId)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($deposit->status, ['credited', 'reorged'], true)) {
                throw new DomainException('Only credited or reorged deposits can be reversed.');
            }

            if (empty($deposit->credited_operation_id)) {
                return;
            }

            if (! empty($deposit->reversal_operation_id)) {
                return;
            }

            $userAccount = EloquentAccount::query()
                ->where('owner_type', 'user')
                ->where('owner_id', $deposit->user_id)
                ->where('currency_network_id', $deposit->currency_network_id)
                ->lockForUpdate()
                ->firstOrFail();

            $clearing = $this->systemAccounts->resolveByCode('clearing', $deposit->currency_network_id);

            $operationId = 'deposit-reversal:' . $deposit->id;

            $this->posting->post(
                idempotencyKey: $operationId,
                type: 'deposit_reversal',
                referenceType: 'deposit',
                referenceId: $depositId,
                lines: [
                    LedgerPostingLine::debit($userAccount->id, (string) $deposit->amount, ['side' => 'user']),
                    LedgerPostingLine::credit($clearing->id(), (string) $deposit->amount, ['side' => 'clearing']),
                ],
                metadata: $metadata
            );

            $deposit->status = 'reversed';
            $deposit->reversed_at = now();
            $deposit->reversal_operation_id = $operationId;
            $deposit->reversal_reason = $metadata['reason'] ?? 'blockchain_reorg';
            $deposit->save();
        });
    }

    public function reserveFunds(
        int $userId,
        int $currencyNetworkId,
        string $amount,
        string $operationId,
        string $referenceType,
        ?int $referenceId = null,
        array $metadata = [],
        ?int $expiresInSeconds = null
    ): void {
        DB::transaction(function () use (
            $userId,
            $currencyNetworkId,
            $amount,
            $operationId,
            $referenceType,
            $referenceId,
            $metadata,
            $expiresInSeconds
        ): void {

            /**
             * 1) create/find operation header
             * operationId = idempotency key
             */
            $operation = $this->findOrCreateOperation( //create LedgerOperation
                idempotencyKey: $operationId,  //'withdrawal:' . $withdrawal->id()->value() . ':reserve';
                type: 'withdrawal_reserve',
                referenceType: $referenceType, //'withdrawal'
                referenceId: $referenceId,     //$withdrawal->id()->value()
                metadata: $metadata
            );

            if ($operation->status === 'posted') {
                return;
            }

            /**
             * 2) lock user account
             */
            $account = EloquentAccount::query()
                ->where('owner_type', 'user')
                ->where('owner_id', $userId)
                ->where('currency_network_id', $currencyNetworkId)
                ->lockForUpdate()
                ->firstOrFail();

            $available = bcsub((string) $account->balance, (string) $account->reserved_balance, 18);

            if (bccomp($available, $amount, 18) < 0) {
                throw new DomainException('Insufficient available balance to reserve.');
            }

            /**
             * 3) create hold linked to ledger_operations.id
             * IMPORTANT:
             * ledger_operation_id = UUID primary key of ledger_operations
             */
            EloquentLedgerHold::query()->create([
                'ledger_operation_id' => $operation->id,
                'account_id' => $account->id,
                'currency_network_id' => $currencyNetworkId,
                'amount' => $amount,
                'status' => 'active',
                'reason' => 'withdrawal',
                'expires_at' => $expiresInSeconds ? now()->addSeconds($expiresInSeconds) : null, // 900 sec = 15 min //denis  //needs to create a service for validate expires_at!!!!!!!!!
                'metadata' => array_merge($metadata, [
                    'reference_type' => $referenceType,
                    'reference_id' => $referenceId,
                    'operation_idempotency_key' => $operationId,
                ]),
            ]);

            /**
             * 4) update reserved_balance only
             */
            $account->reserved_balance = bcadd((string) $account->reserved_balance, $amount, 18);
            $account->version = ((int) $account->version) + 1;
            $account->save();

            /**
             * 5) mark operation posted
             */
            $operation->status = 'posted';
            $operation->posted_at = now();
            $operation->save();
        });
    }

    public function releaseFunds(
        int $holdId,
        string $operationId,
        string $referenceType,
        ?int $referenceId = null,
        array $metadata = []
    ): void {
        DB::transaction(function () use ($holdId, $operationId, $referenceType, $referenceId, $metadata): void {
            $operation = $this->findOrCreateOperation(
                idempotencyKey: $operationId,
                type: 'withdrawal_release',
                referenceType: $referenceType,
                referenceId: $referenceId,
                metadata: $metadata
            );

            if ($operation->status === 'posted') {
                return;
            }

            $hold = EloquentLedgerHold::query()
                ->whereKey($holdId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($hold->status !== 'active') {
                return;
            }

            $account = EloquentAccount::query()
                ->whereKey($hold->account_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (bccomp((string) $account->reserved_balance, (string) $hold->amount, 18) < 0) {
                throw new DomainException('Reserved balance is less than hold amount.');
            }

            $account->reserved_balance = bcsub((string) $account->reserved_balance, (string) $hold->amount, 18);
            $account->version = ((int) $account->version) + 1;
            $account->save();

            $hold->status = 'released';
            $hold->released_at = now();
            $hold->metadata = array_merge((array) $hold->metadata, [
                'release_operation_idempotency_key' => $operationId,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
            ]);
            $hold->save();

            $withdrawal = EloquentWithdrawal::query()
                ->where('ledger_hold_id', $hold->id)
                ->lockForUpdate()
                ->first();

            if ($withdrawal) {
                $withdrawal->release_operation_id = $operationId;
                $withdrawal->released_at = now();
                $withdrawal->status = 'released';
                $withdrawal->save();
            }

            $operation->status = 'posted';
            $operation->posted_at = now();
            $operation->save();
        });
    }

    /**
     * LedgerService::consumeHold():
     *
     * уменьшает reserved_balance
     * пишет double-entry через LedgerPostingService
     * hold → consumed
     * withdrawal → settled
     *
     *
     * example:
     * Если пользователь выводит 10 ETH и fee 2 ETH, то:
     *
     * user debit = 12
     * clearing credit = 10
     * fee_income credit = 2
     */
    public function consumeHold(
        int $holdId,
        string $operationId,
        string $referenceType,
        ?int $referenceId = null,
        array $metadata = []
    ): void {
        DB::transaction(function () use ($holdId, $operationId, $referenceType, $referenceId, $metadata): void {
            $operation = $this->findOrCreateOperation(
                idempotencyKey: $operationId,
                type: 'withdrawal_consume',
                referenceType: $referenceType,
                referenceId: $referenceId,
                metadata: $metadata
            );

            if ($operation->status === 'posted') {
                return;
            }

            $hold = EloquentLedgerHold::query()
                ->whereKey($holdId)
                ->lockForUpdate()
                ->firstOrFail();

            $withdrawal = EloquentWithdrawal::query()
                ->where('ledger_hold_id', $hold->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($withdrawal->consume_operation_id === $operationId || in_array($withdrawal->status, ['settled', 'confirmed', 'reversed'], true)) {
                return;
            }

            if (bccomp((string) $hold->amount, (string) $withdrawal->total_debit_amount, 18) !== 0) {
                throw new DomainException('Hold amount and withdrawal total debit amount mismatch.');
            }

            if ($hold->status !== 'active') {
                throw new DomainException('Only active hold can be consumed.');
            }

            $account = EloquentAccount::query()
                ->whereKey($hold->account_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (bccomp((string) $account->reserved_balance, (string) $hold->amount, 18) < 0) {
                throw new DomainException('Reserved balance is less than hold amount.');
            }

            $clearing = $this->systemAccounts->resolveByCode('clearing', $hold->currency_network_id);
            $feeIncome = $this->systemAccounts->resolveByCode('fee_income', $hold->currency_network_id);
            /**
             * 3) double-entry posting:
             *    - user debit
             *    - clearing/settlement credit
             *
             * IMPORTANT:
             * - posting service changes balance
             * - this method must NOT manually subtract balance
             *
             *  Posting happens inside the same DB transaction.
             *  No nested DB::transaction inside posting service.
             */
            $ledgerOperationId = $this->posting->post(
                idempotencyKey: $operationId,
                type: 'withdrawal_consume',
                referenceType: $referenceType,
                referenceId: $referenceId,
                lines: [
                    LedgerPostingLine::debit($account->id, (string) $withdrawal->total_debit_amount, [
                        'side' => 'user',
                        'hold_id' => $hold->id,
                    ]),
                    LedgerPostingLine::credit($clearing->id(), (string) $withdrawal->amount, [
                        'side' => 'clearing',
                        'withdrawal_id' => $withdrawal->id,
                    ]),
                    LedgerPostingLine::credit($feeIncome->id(), (string) $withdrawal->fee_amount, [
                        'side' => 'fee_income',
                        'withdrawal_id' => $withdrawal->id,
                    ]),
                ],
                metadata: $metadata
            );
            /**
             * 4) only after successful posting:
             *    decrease reserved_balance
             *    do NOT touch balance here
             */
            //            $freshAccount = EloquentAccount::query()
//                ->whereKey($userAccount->id)
//                ->lockForUpdate()
//                ->firstOrFail();
//
//            if (bccomp((string) $freshAccount->reserved_balance, (string) $hold->amount, 18) < 0) {
//                throw new DomainException('Reserved balance is less than hold amount.');
//            }
//
//            $freshAccount->reserved_balance = bcsub((string) $freshAccount->reserved_balance, (string) $hold->amount, 18);
//            $freshAccount->version = ((int) $freshAccount->version) + 1;
//            $freshAccount->save();
            $account->reserved_balance = bcsub((string) $account->reserved_balance, (string) $hold->amount, 18);
            $account->version = ((int) $account->version) + 1;
            $account->save();
            /**
             * 5) mark hold consumed
             */
            $hold->status = 'consumed';
            $hold->consumed_at = now();
            $hold->metadata = array_merge((array) $hold->metadata, [
                'consume_operation_id' => $operationId,
                'ledger_operation_uuid' => $ledgerOperationId,
            ]);
            $hold->save();

            $withdrawal->consume_operation_id = $operationId;
            $withdrawal->settled_at = now();
            $withdrawal->status = 'settled';
            $withdrawal->save();

            $operation->status = 'posted';
            $operation->posted_at = now();
            $operation->save();
        });
    }

    /**
     * @param int $withdrawalId
     * @param int $currencyNetworkId
     * @param string $amount
     * @param string $operationId
     * @param array $metadata
     * @return void
     *
     * вызываем recordWithdrawalNetworkFeeExpense:
     *
     * из UpdateWithdrawalConfirmationsHandler
     * после confirmation/finality, когда сеть уже стабильно приняла tx,
     * когда tx уже confirmed/finalized и actual fee известна .
     *
     * recordWithdrawalNetworkFeeExpense() - Network fee Use only native currency
     */
    public function recordWithdrawalNetworkFeeExpense(
        int $withdrawalId,
        int $currencyNetworkId,
        string $amount,
        string $operationId,
        array $metadata = []
    ): void {
        DB::transaction(function () use ($withdrawalId, $currencyNetworkId, $amount, $operationId, $metadata): void {
            if (bccomp($amount, '0', 18) <= 0) {
                return;
            }

            $withdrawal = EloquentWithdrawal::query()
                ->whereKey($withdrawalId)
                ->lockForUpdate()
                ->firstOrFail();

            if (! empty($withdrawal->network_fee_posted_at)) {
                return;
            }

            $hotWallet = $this->systemAccounts->resolveByCode('hot_wallet', $currencyNetworkId);
            $expense = $this->systemAccounts->resolveByCode('network_fee_expense', $currencyNetworkId);

            $this->posting->post(
                idempotencyKey: $operationId,
                type: 'withdrawal_network_fee',
                referenceType: 'withdrawal',
                referenceId: $withdrawalId,
                lines: [
                    LedgerPostingLine::debit($hotWallet->id(), $amount, [
                        'side' => 'hot_wallet',
                        'withdrawal_id' => $withdrawalId,
                    ]),
                    LedgerPostingLine::credit($expense->id(), $amount, [
                        'side' => 'network_fee_expense',
                        'withdrawal_id' => $withdrawalId,
                    ]),
                ],
                metadata: $metadata
            );

            $withdrawal->network_fee_currency_network_id = $currencyNetworkId;
            $withdrawal->network_fee_posted_at = now();
            $withdrawal->network_fee_actual_amount = $amount;
            $withdrawal->network_fee_operation_id = $operationId;
            $withdrawal->save();
        });
    }

    /**
     * reverseWithdrawalConsumption:
     *
     * principal возвращается пользователю;
     * withdrawal fee тоже возвращается пользователю;
     * чистый доход платформы отменяется;
     * withdrawal получает terminal compensation state reversed.
     *
     *
     * reverseWithdrawalNetworkFeeExpense() when and where to call it:
     * When:
     * Only when the withdrawal already had:
     * network_fee_posted_at set,
     * network_fee_operation_id set.
     * That means gas expense was already booked as canonical ledger fact.
     * Where:
     * Inside reverseWithdrawalConsumption() after reversing principal + fee income.
     * Why
     * Because:
     * if reorg happened before consume, network fee should not be booked yet;
     * if it was already booked, reorg must reverse it too.
     */
    public function reverseWithdrawalConsumption(
        int $withdrawalId,
        array $metadata = []
    ): void {

        DB::transaction(function () use ($withdrawalId, $metadata): void {
            $withdrawal = EloquentWithdrawal::query()
                ->whereKey($withdrawalId)
                ->lockForUpdate()
                ->firstOrFail();

            if (empty($withdrawal->consume_operation_id)) {
                return;
            }

            if (! empty($withdrawal->reversal_operation_id)) {
                return;
            }

            $userAccount = EloquentAccount::query()
                ->where('owner_type', 'user')
                ->where('owner_id', $withdrawal->user_id)
                ->where('currency_network_id', $withdrawal->currency_network_id)
                ->lockForUpdate()
                ->firstOrFail();

            $clearing = $this->systemAccounts->resolveByCode('clearing', $withdrawal->currency_network_id);
            $feeIncome = $this->systemAccounts->resolveByCode('fee_income', $withdrawal->currency_network_id);

            $operationId = 'withdrawal:' . $withdrawal->id . ':reversal';

            $this->posting->post(
                idempotencyKey: $operationId,
                type: 'withdrawal_reversal',
                referenceType: 'withdrawal',
                referenceId: $withdrawalId,
                lines: [
                    LedgerPostingLine::debit($clearing->id(), (string) $withdrawal->amount, [
                        'side' => 'clearing',
                        'withdrawal_id' => $withdrawal->id,
                    ]),
                    LedgerPostingLine::debit($feeIncome->id(), (string) $withdrawal->fee_amount, [
                        'side' => 'fee_income',
                        'withdrawal_id' => $withdrawal->id,
                    ]),
                    LedgerPostingLine::credit($userAccount->id, (string) $withdrawal->total_debit_amount, [
                        'side' => 'user',
                        'withdrawal_id' => $withdrawal->id,
                    ]),
                ],
                metadata: $metadata
            );

            $withdrawal->reversal_operation_id = $operationId;
            $withdrawal->reversed_at = now();
            $withdrawal->reversal_reason = $metadata['reason'] ?? 'chain_reorg';
            $withdrawal->status = 'reversed';
            $withdrawal->save();
//            //     * Reverse network fee if reorg happens after booking
//            if (! empty($withdrawal->network_fee_posted_at) && ! empty($withdrawal->network_fee_operation_id)) {
//                $this->reverseWithdrawalNetworkFeeExpense(
//                    withdrawalId: $withdrawalId,
//                    metadata: array_merge($metadata, [
//                        'reason' => 'withdrawal_reorg',
//                        'source' => 'reverse_withdrawal_consumption',
//                    ])
//                );
//            }
        });
    }


    public function reverseWithdrawalNetworkFeeExpense(
        int $withdrawalId,
        array $metadata = []
    ): void {
//        DB::transaction(function () use ($withdrawalId, $metadata): void {
//            $withdrawal = EloquentWithdrawal::query()
//                ->whereKey($withdrawalId)
//                ->lockForUpdate()
//                ->firstOrFail();
//
//            if (empty($withdrawal->network_fee_posted_at) || empty($withdrawal->network_fee_operation_id)) {
//                return;
//            }
//
//            if (! empty($withdrawal->network_fee_reversal_operation_id)) {
//                return;
//            }
//
//            if (empty($withdrawal->network_fee_currency_network_id)) {
//                throw new DomainException('Missing network fee currency network id.');
//            }
//
//            $hotWallet = $this->systemAccounts->resolveByCode('hot_wallet', (int) $withdrawal->network_fee_currency_network_id);
//            $expense = $this->systemAccounts->resolveByCode('network_fee_expense', (int) $withdrawal->network_fee_currency_network_id);
//
//            $opId = 'withdrawal:' . $withdrawalId . ':network_fee:reversal';
//
//            $this->posting->post(
//                idempotencyKey: $opId,
//                type: 'withdrawal_network_fee_reversal',
//                referenceType: 'withdrawal',
//                referenceId: $withdrawalId,
//                lines: [
//                    LedgerPostingLine::credit($hotWallet->id(), (string) $withdrawal->network_fee_actual_amount, [
//                        'side' => 'hot_wallet',
//                    ]),
//                    LedgerPostingLine::debit($expense->id(), (string) $withdrawal->network_fee_actual_amount, [
//                        'side' => 'network_fee_expense',
//                    ]),
//                ],
//                metadata: $metadata
//            );
//
//            $withdrawal->network_fee_reversal_operation_id = $opId;
//            $withdrawal->save();
//        });
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
            return EloquentLedgerOperation::query()->create([
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

    public function transferInternal(
        int $fromUserId,
        int $toUserId,
        int $currencyNetworkId,
        string $amount,
        string $operationId,
        string $referenceType,
        ?int $referenceId = null,
        array $metadata = []
    ): void {
        DB::transaction(function () use (
            $fromUserId,
            $toUserId,
            $currencyNetworkId,
            $amount,
            $operationId,
            $referenceType,
            $referenceId,
            $metadata
        ): void {
            if ($fromUserId === $toUserId) {
                throw new DomainException('Self transfer is not allowed.');
            }

            $sender = EloquentAccount::query()
                ->where('owner_type', 'user')
                ->where('owner_id', $fromUserId)
                ->where('currency_network_id', $currencyNetworkId)
                ->lockForUpdate()
                ->firstOrFail();

            $receiver = EloquentAccount::query()
                ->where('owner_type', 'user')
                ->where('owner_id', $toUserId)
                ->where('currency_network_id', $currencyNetworkId)
                ->lockForUpdate()
                ->first();

            if (! $receiver) {
                $receiver = EloquentAccount::create([
                    'owner_type' => 'user',
                    'owner_id' => $toUserId,
                    'currency_network_id' => $currencyNetworkId,
                    'balance' => '0',
                    'reserved_balance' => '0',
                    'status' => 'active',
                    'version' => 0,
                    'metadata' => [],
                ]);
            }

            $this->posting->post(
                idempotencyKey: $operationId,
                type: 'internal_transfer',
                referenceType: $referenceType,
                referenceId: $referenceId,
                lines: [
                    LedgerPostingLine::debit($sender->id, $amount, ['side' => 'sender']),
                    LedgerPostingLine::credit($receiver->id, $amount, ['side' => 'receiver']),
                ],
                metadata: $metadata
            );
        });
    }

    public function moveToSuspense( //admin feature
        int $userId,
        int $currencyNetworkId,
        string $amount,
        string $operationId,
        string $reason,
        ?int $referenceId = null,
        array $metadata = []
    ): void {
        DB::transaction(function () use (
            $userId,
            $currencyNetworkId,
            $amount,
            $operationId,
            $reason,
            $referenceId,
            $metadata
        ): void {
            $userAccount = EloquentAccount::query()
                ->where('owner_type', 'user')
                ->where('owner_id', $userId)
                ->where('currency_network_id', $currencyNetworkId)
                ->lockForUpdate()
                ->firstOrFail();

            $available = bcsub((string) $userAccount->balance, (string) $userAccount->reserved_balance, 18);

            if (bccomp($available, $amount, 18) < 0) {
                throw new DomainException('Insufficient available balance to move to suspense.');
            }

            $suspense = $this->systemAccounts->resolveByCode('suspense', $currencyNetworkId);

            $this->posting->post(
                idempotencyKey: $operationId,
                type: 'move_to_suspense',
                referenceType: 'manual_adjustment',
                referenceId: $referenceId,
                lines: [
                    LedgerPostingLine::debit($userAccount->id, $amount, ['reason' => $reason]),
                    LedgerPostingLine::credit($suspense->id(), $amount, ['reason' => $reason]),
                ],
                metadata: array_merge($metadata, [
                    'reason' => $reason,
                    'mode' => 'to_suspense',
                ])
            );
        });
    }

    public function releaseFromSuspense(//admin feature
        int $userId,
        int $currencyNetworkId,
        string $amount,
        string $operationId,
        string $reason,
        ?int $referenceId = null,
        array $metadata = []
    ): void {
        DB::transaction(function () use (
            $userId,
            $currencyNetworkId,
            $amount,
            $operationId,
            $reason,
            $referenceId,
            $metadata
        ): void {
            $userAccount = EloquentAccount::query()
                ->where('owner_type', 'user')
                ->where('owner_id', $userId)
                ->where('currency_network_id', $currencyNetworkId)
                ->lockForUpdate()
                ->firstOrFail();

            $suspense = $this->systemAccounts->resolveByCode('suspense', $currencyNetworkId);

            $this->posting->post(
                idempotencyKey: $operationId,
                type: 'release_from_suspense',
                referenceType: 'manual_adjustment',
                referenceId: $referenceId,
                lines: [
                    LedgerPostingLine::debit($suspense->id(), $amount, ['reason' => $reason]),
                    LedgerPostingLine::credit($userAccount->id, $amount, ['reason' => $reason]),
                ],
                metadata: array_merge($metadata, [
                    'reason' => $reason,
                    'mode' => 'from_suspense',
                ])
            );
        });
    }
}
