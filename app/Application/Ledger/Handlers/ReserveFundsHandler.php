<?php

declare(strict_types=1);

namespace App\Application\Ledger\Handlers;

use App\Application\Ledger\Commands\ReserveFundsCommand;
use App\Domain\Ledger\Entities\Account;
use App\Domain\Ledger\Entities\LedgerOperation;
use App\Domain\Ledger\Entities\LedgerHold;
use App\Domain\Ledger\Repositories\AccountRepository;
use App\Domain\Ledger\Repositories\LedgerHoldRepository;
use App\Domain\Ledger\Repositories\LedgerOperationRepository;
use App\Domain\Ledger\ValueObjects\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * ReserveFundsHandler
 *
 * Назначение:
 * - перевести деньги из available -> reserved;
 * - создать hold, который будет жить до release/consume.
 *
 * Почему отдельный handler:
 * - withdrawal — это многошаговый процесс;
 * - reserve — не final debit;
 * - нужна явная доменная фиксация hold.
 */
final class ReserveFundsHandler
{
    public function __construct(
        private readonly AccountRepository $accounts,
        private readonly LedgerOperationRepository $operations,
        private readonly LedgerHoldRepository $holds,
    ) {}

    public function handle(ReserveFundsCommand $command): void
    {
        DB::transaction(function () use ($command) {
            /**
             * Идемпотентность важна, потому что:
             * - повторный webhook;
             * - повторный API request;
             * - retry очереди.
             */
            $existingOperation = $this->operations->findByIdempotencyKey($command->idempotencyKey);

            if ($existingOperation !== null && $existingOperation->status() === 'posted') {
                return;
            }

            $operation = $existingOperation ?? new LedgerOperation(
                id: (string) Str::uuid(),
                idempotencyKey: $command->idempotencyKey,
                type: 'reserve',
                status: 'pending',
                referenceType: $command->referenceType,
                referenceId: $command->referenceId,
                description: $command->description,
                metadata: $command->metadata,
            );

            $account = $this->accounts->findByOwnerAndCurrencyNetwork(
                $command->ownerType,
                $command->ownerId,
                $command->currencyNetworkId
            );

            if ($account === null) {
                throw new \DomainException('Account does not exist for reserve operation.');
            }

            /**
             * Лочим account через отдельный getByIdForUpdate.
             * Это защищает от гонок на balance/reserved_balance.
             */
            $account = $this->accounts->getByIdForUpdate($account->id());

            if ($account === null) {
                throw new \DomainException('Account not found under lock.');
            }

            $amount = new Money($command->amount);

            /**
             * Доменное правило:
             * reserve можно делать только из доступного баланса.
             */
            $account->reserve($amount);

            /**
             * Обновляем account.
             * Здесь меняется только reserved_balance.
             */
            $savedAccount = $this->accounts->save($account);

            /**
             * Создаём hold.
             * Именно он объясняет, почему деньги стали недоступны.
             */
            $hold = new LedgerHold(
                id: null,
                ledgerOperationId: $operation->id(),
                accountId: $savedAccount->id(),
                currencyNetworkId: $savedAccount->currencyNetworkId(),
                amount: $amount->amount,
                status: 'active',
                reason: $command->reason,
                expiresAt: $command->expiresInSeconds
                    ? now()->addSeconds($command->expiresInSeconds)->toDateTimeString()
                    : null,
                metadata: array_merge($command->metadata, [
                    'reserved_for' => $command->referenceType,
                    'reserved_reference_id' => $command->referenceId,
                ]),
            );

            $this->holds->save($hold);

            $operation->markPosted();
            $this->operations->save($operation);
        });
    }
}
