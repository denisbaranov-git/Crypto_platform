<?php

declare(strict_types=1);

namespace App\Application\Ledger\Handlers;

use App\Application\Ledger\Commands\ConsumeHoldCommand;
use App\Domain\Ledger\Entities\LedgerOperation;
use App\Domain\Ledger\Repositories\AccountRepository;
use App\Domain\Ledger\Repositories\LedgerHoldRepository;
use App\Domain\Ledger\Repositories\LedgerOperationRepository;
use App\Domain\Ledger\ValueObjects\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * ConsumeHoldHandler
 *
 * Это уже окончательное списание денег.
 * На этом шаге reserve превращается в final debit.
 */
final class ConsumeHoldHandler
{
    public function __construct(
        private readonly AccountRepository $accounts,
        private readonly LedgerOperationRepository $operations,
        private readonly LedgerHoldRepository $holds,
    ) {}

    public function handle(ConsumeHoldCommand $command): void
    {
        DB::transaction(function () use ($command) {
            $existingOperation = $this->operations->findByIdempotencyKey($command->idempotencyKey);

            if ($existingOperation !== null && $existingOperation->status() === 'posted') {
                return;
            }

            $operation = $existingOperation ?? new LedgerOperation(
                id: (string) Str::uuid(),
                idempotencyKey: $command->idempotencyKey,
                type: 'consume_hold',
                status: 'pending',
                referenceType: $command->referenceType,
                referenceId: $command->referenceId,
                description: $command->description,
                metadata: $command->metadata,
            );

            $hold = $this->holds->findByIdForUpdate($command->holdId);

            if ($hold === null) {
                throw new \DomainException('Hold not found.');
            }

            if ($hold->status() !== 'active') {
                throw new \DomainException('Only active hold can be consumed.');
            }

            $account = $this->accounts->getByIdForUpdate($hold->accountId());

            if ($account === null) {
                throw new \DomainException('Account not found for hold consumption.');
            }

            $amount = new Money($hold->amount());

            /**
             * consumeReservation() делает сразу две вещи:
             * - уменьшает reserved_balance;
             * - уменьшает balance.
             *
             * Это и есть реальный financial debit.
             */
            $balanceBefore = $account->balance()->amount;
            $account->consumeReservation($amount);
            $balanceAfter = $account->balance()->amount;

            $savedAccount = $this->accounts->save($account);

            $hold->consume();
            $this->holds->save($hold);

            /**
             * Тут уже пишем journal entry,
             * потому что balance изменился.
             */
            DB::table('account_transactions')->insert([
                'ledger_operation_id' => $operation->id(),
                'account_id' => $savedAccount->id(),
                'currency_network_id' => $savedAccount->currencyNetworkId(),
                'direction' => 'debit',
                'amount' => $amount->amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'reference_type' => $command->referenceType,
                'reference_id' => $command->referenceId,
                'status' => 'confirmed',
                'metadata' => json_encode(array_merge($command->metadata, [
                    'hold_id' => $hold->id(),
                    'consumed_from_hold' => true,
                ]), JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $operation->markPosted();
            $this->operations->save($operation);
        });
    }
}
