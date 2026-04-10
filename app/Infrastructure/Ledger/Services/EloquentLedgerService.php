<?php

namespace App\Infrastructure\Ledger\Services;

use App\Domain\Ledger\Contracts\LedgerService;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentAccount;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentDeposit;
use App\Models\AccountTransaction;
use Illuminate\Support\Facades\DB;

final class EloquentLedgerService implements LedgerService
{
    public function postDepositCredit(
        int $depositId,
        int $userId,
        int $currencyId,
        string $amount,
        string $operationId,
        array $metadata = []
    ): void {
        DB::transaction(function () use (
            $depositId,
            $userId,
            $currencyId,
            $amount,
            $operationId,
            $metadata
        ) {
            // 1. идемпотентность
            $exists = AccountTransaction::query()
                ->where('operation_id', $operationId)
                ->exists();

            if ($exists) {
                return;
            }

            // 2. lock account
            $account = EloquentAccount::query()
                ->where('user_id', $userId)
                ->where('currency_id', $currencyId)
                ->lockForUpdate()
                ->first();

            if (! $account) {
                $account = EloquentAccount::create([
                    'user_id' => $userId,
                    'currency_id' => $currencyId,
                    'balance' => 0,
                ]);

                $account->refresh();
            }

            $before = $account->balance;
            $after = bcadd($before, $amount, 18);

            // 3. обновляем баланс
            $account->balance = $after;
            $account->save();

            // 4. ledger запись
            AccountTransaction::create([
                'operation_id' => $operationId,
                'user_id' => $userId,
                'currency_id' => $currencyId,
                'type' => 'deposit',
                'reference_type' => 'deposit',
                'reference_id' => $depositId,
                'amount' => $amount,
                'balance_before' => $before,
                'balance_after' => $after,
                'status' => 'confirmed',
                'metadata' => $metadata,
            ]);

            // 5. mark deposit credited
            EloquentDeposit::query()
                ->where('id', $depositId)
                ->update([
                    'status' => 'credited',
                    'credited_at' => now(),
                ]);
        });
    }

    public function reverseDepositCredit(
        int $depositId,
        string $operationId,
        array $metadata = []
    ): void {
        DB::transaction(function () use ($depositId, $operationId, $metadata) {

            $deposit = EloquentDeposit::lockForUpdate()->findOrFail($depositId);

            $reverseOp = 'reversal:' . $operationId;

            $exists = DB::table('account_transactions')
                ->where('operation_id', $reverseOp)
                ->exists();

            if ($exists) {
                return;
            }

            $account = EloquentAccount::query()
                ->where('user_id', $deposit->user_id)
                ->where('currency_id', $deposit->currency_id)
                ->lockForUpdate()
                ->firstOrFail();

            $before = $account->balance;
            $after = bcsub($before, $deposit->amount, 18);

            $account->update([
                'balance' => $after,
            ]);

            DB::table('account_transactions')->insert([
                'operation_id' => $reverseOp,
                'user_id' => $deposit->user_id,
                'currency_id' => $deposit->currency_id,
                'type' => 'deposit_reversal',
                'reference_type' => 'deposit',
                'reference_id' => $deposit->id,
                'amount' => '-' . $deposit->amount,
                'balance_before' => $before,
                'balance_after' => $after,
                'status' => 'confirmed',
                'metadata' => json_encode($metadata),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }
}
