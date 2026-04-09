<?php

declare(strict_types=1);

namespace App\Application\Ledger\Handlers;

use App\Application\Ledger\Commands\CreditAccountCommand;
use App\Domain\Ledger\Entities\Account;
use App\Domain\Ledger\Entities\LedgerOperation;
use App\Domain\Ledger\Repositories\AccountRepository;
use App\Domain\Ledger\Repositories\LedgerOperationRepository;
use App\Domain\Ledger\ValueObjects\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * CreditAccountHandler
 *
 * Что делает:
 * - запускает DB transaction;
 * - проверяет идемпотентность;
 * - находит или создаёт account;
 * - credit() balance;
 * - сохраняет account;
 * - создаёт ledger operation;
 * - пишет transaction journal (в коде ниже я покажу это тоже).
 *
 * Почему это application layer:
 * - он оркестрирует use case;
 * - он не содержит сложных доменных правил;
 * - он не знает деталей SQL;
 * - он не является инфраструктурой.
 */
final class CreditAccountHandler
{
    public function __construct(
        private readonly AccountRepository $accounts,
        private readonly LedgerOperationRepository $operations,
    ) {}

    public function handle(CreditAccountCommand $command): void
    {
        DB::transaction(function () use ($command) {
            /**
             * 1) Идемпотентность.
             * Если операция уже была выполнена, второй раз баланс менять нельзя.
             */
            $existingOperation = $this->operations->findByIdempotencyKey($command->idempotencyKey);

            if ($existingOperation !== null && $existingOperation->status() === 'posted') {
                return;
            }

            /**
             * 2) Создаём operation, если её ещё нет.
             * Это шапка проводки, а не сама проводка.
             */
            $operation = $existingOperation ?? new LedgerOperation(
                id: (string) Str::uuid(),
                idempotencyKey: $command->idempotencyKey,
                type: 'credit',
                status: 'pending',
                referenceType: $command->referenceType,
                referenceId: $command->referenceId,
                description: $command->description,
                metadata: $command->metadata,
            );

            /**
             * 3) Загружаем account.
             * Для write path лучше делать row lock на уровне репозитория.
             * Если account ещё не создан — создаём его lazily.
             */
            $account = $this->accounts->findByOwnerAndCurrencyNetwork(
                $command->ownerType,
                $command->ownerId,
                $command->currencyNetworkId
            );

            if ($account === null) {
                $account = new Account(
                    id: null,
                    ownerType: $command->ownerType,
                    ownerId: $command->ownerId,
                    currencyNetworkId: $command->currencyNetworkId,
                    balance: Money::zero(),
                    reservedBalance: Money::zero(),
                    status: 'active',
                    version: 0,
                    metadata: [],
                );
            }

            /**
             * 4) Меняем доменное состояние.
             */
            $amount = new Money($command->amount);
            $balanceBefore = $account->balance()->amount;

            $account->credit($amount);

            $balanceAfter = $account->balance()->amount;

            /**
             * 5) Сохраняем account.
             */
            $savedAccount = $this->accounts->save($account);

            /**
             * 6) Сохраняем operation как posted.
             */
            $operation->markPosted();
            $this->operations->save($operation);

            /**
             * 7) Пишем journal entry.
             * Это immutable история.
             * Одно начисление = одна строка.
             *
             * Если ты хочешь ещё чище, можно вынести это в отдельный
             * LedgerPostingService, но для старта этот вариант хорош.
             */
            DB::table('account_transactions')->insert([
                'ledger_operation_id' => $operation->id(),
                'account_id' => $savedAccount->id(),
                'currency_network_id' => $savedAccount->currencyNetworkId(),
                'direction' => 'credit',
                'amount' => $amount->amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'reference_type' => $command->referenceType,
                'reference_id' => $command->referenceId,
                'status' => 'confirmed',
                'metadata' => json_encode($command->metadata, JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }
}
