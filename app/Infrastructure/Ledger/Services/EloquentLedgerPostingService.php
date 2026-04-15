<?php

declare(strict_types=1);

namespace App\Infrastructure\Ledger\Services;

use App\Domain\Ledger\Contracts\LedgerPostingService;
use App\Domain\Ledger\ValueObjects\LedgerDirection;
use App\Domain\Ledger\ValueObjects\LedgerPostingLine;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentAccount;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentAccountTransaction;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentLedgerOperation;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

final class EloquentLedgerPostingService implements LedgerPostingService
{
    /**
     * @param LedgerPostingLine[] $lines
     *
     * Важно:
     * - метод не создаёт DB::transaction();
     * - вызывается только из use-case service внутри уже открытой транзакции.
     */
    public function post(
        string $idempotencyKey,
        string $type,
        ?string $referenceType,
        ?int $referenceId,
        array $lines,
        array $metadata = []
    ): string {
        if (count($lines) < 2) {
            throw new DomainException('Double-entry posting requires at least two lines.');
        }

        $operation = EloquentLedgerOperation::query()
            ->where('idempotency_key', $idempotencyKey)
            ->lockForUpdate()
            ->first();

        if ($operation && $operation->status === 'posted') {
            return $operation->id;
        }

        if (! $operation) {
            try {
                $operation = EloquentLedgerOperation::create([
                    'id' => (string) Str::uuid(),
                    'idempotency_key' => $idempotencyKey,
                    'type' => $type,
                    'reference_type' => $referenceType,
                    'reference_id' => $referenceId,
                    'status' => 'pending',
                    'metadata' => $metadata,
                ]);
            } catch (QueryException $e) {
                $operation = EloquentLedgerOperation::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->firstOrFail();
            }
        }

        if ($operation->status === 'posted') {
            return $operation->id;
        }

        $totalDebit = '0';
        $totalCredit = '0';

        foreach ($lines as $line) {
            if (! $line instanceof LedgerPostingLine) {
                throw new DomainException('All posting lines must be LedgerPostingLine instances.');
            }

            if ($line->direction === LedgerDirection::Debit) {
                $totalDebit = bcadd($totalDebit, $line->amount, 18);
            } else {
                $totalCredit = bcadd($totalCredit, $line->amount, 18);
            }
        }

        if (bccomp($totalDebit, $totalCredit, 18) !== 0) {
            throw new DomainException("Unbalanced posting operation. debit=$totalDebit credit=$totalCredit");
        }

        $accountIds = array_values(array_unique(array_map(
            static fn (LedgerPostingLine $line) => $line->accountId,
            $lines
        )));

        sort($accountIds);

        $accounts = EloquentAccount::query()
            ->whereIn('id', $accountIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($accountIds as $accountId) {
            if (! $accounts->has($accountId)) {
                throw new DomainException("Account [$accountId] not found for posting.");
            }
        }

        $networkIds = $accounts->pluck('currency_network_id')->unique()->values();
        if ($networkIds->count() !== 1) {
            throw new DomainException('All posting lines must belong to the same currency network.');
        }

        $journalRows = [];

        foreach ($lines as $line) {
            /** @var EloquentAccount $account */
            $account = $accounts->get($line->accountId);

            $before = (string) $account->balance;

            if ($line->direction === LedgerDirection::Credit) {
                $after = bcadd($before, $line->amount, 18);
            } else {
                if (bccomp($before, $line->amount, 18) < 0) {
                    throw new DomainException("Insufficient balance on account [{$account->id}] for debit.");
                }

                $after = bcsub($before, $line->amount, 18);
            }

            $account->balance = $after;
            $account->version = ((int) $account->version) + 1;
            $account->save();

            $journalRows[] = [
                'ledger_operation_id' => $operation->id,
                'account_id' => $account->id,
                'currency_network_id' => $account->currency_network_id,
                'direction' => $line->direction->value,
                'amount' => $line->amount,
                'balance_before' => $before,
                'balance_after' => $after,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'status' => 'confirmed',
                'metadata' => array_merge($metadata, $line->metadata),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        EloquentAccountTransaction::insert($journalRows);

        $operation->status = 'posted';
        $operation->posted_at = now();
        $operation->save();

        return $operation->id;
    }
}
