<?php

declare(strict_types=1);

namespace App\Application\Ledger\Handlers;

use App\Application\Ledger\Commands\TransferFundsCommand;
use App\Domain\Ledger\Entities\LedgerOperation;
use App\Domain\Ledger\Repositories\AccountRepository;
use App\Domain\Ledger\Repositories\LedgerOperationRepository;
use App\Domain\Ledger\ValueObjects\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * TransferFundsHandler
 *
 * Одна операция = две проводки:
 * - debit sender
 * - credit receiver
 *
 * Почему так:
 * - это внутренний перевод, а не внешний вывод;
 * - деньги не выходят из системы;
 * - сумма по системе сохраняется.
 */
final class TransferFundsHandler
{
    public function __construct(
        private readonly AccountRepository $accounts,
        private readonly LedgerOperationRepository $operations,
    ) {}

    public function handle(TransferFundsCommand $command): void
    {
        DB::transaction(function () use ($command) {
            if (
                $command->fromOwnerType === $command->toOwnerType
                && $command->fromOwnerId === $command->toOwnerId
            ) {
                throw new \DomainException('Transfer to the same account is not allowed.');
            }

            $existingOperation = $this->operations->findByIdempotencyKey($command->idempotencyKey);

            if ($existingOperation !== null && $existingOperation->status() === 'posted') {
                return;
            }

            $operation = $existingOperation ?? new LedgerOperation(
                id: (string) Str::uuid(),
                idempotencyKey: $command->idempotencyKey,
                type: 'transfer',
                status: 'pending',
                referenceType: $command->referenceType,
                referenceId: $command->referenceId,
                description: $command->description,
                metadata: $command->metadata,
            );

            $sender = $this->accounts->findByOwnerAndCurrencyNetwork(
                $command->fromOwnerType,
                $command->fromOwnerId,
                $command->currencyNetworkId
            );

            if ($sender === null) {
                throw new \DomainException('Sender account not found.');
            }

            $receiver = $this->accounts->findByOwnerAndCurrencyNetwork(
                $command->toOwnerType,
                $command->toOwnerId,
                $command->currencyNetworkId
            );

            /**
             * Receiver можно создать lazily.
             * Это удобно, если на внутренний перевод кошелёк ещё не создан.
             */
            if ($receiver === null) {
                $receiver = new \App\Domain\Ledger\Entities\Account(
                    id: null,
                    ownerType: $command->toOwnerType,
                    ownerId: $command->toOwnerId,
                    currencyNetworkId: $command->currencyNetworkId,
                    balance: Money::zero(),
                    reservedBalance: Money::zero(),
                    status: 'active',
                    version: 0,
                    metadata: [],
                );

                $receiver = $this->accounts->save($receiver);
            }

            /**
             * DETERMINISTIC LOCK ORDER.
             *
             * Почему это важно:
             * - если два потока переводят деньги между A и B в разные стороны,
             *   лочить в разном порядке опасно;
             * - сортировка по ID уменьшает вероятность deadlock.
             *
             * Замечание:
             * - account objects уже есть, но для write path мы переоткрываем их под lock.
             */
            $ids = collect([$sender->id(), $receiver->id()])
                ->filter()
                ->unique()
                ->sort()
                ->values()
                ->all();

            if (count($ids) !== 2) {
                throw new \DomainException('Transfer requires two different accounts.');
            }

            $lockedAccounts = [];
            foreach ($ids as $id) {
                $locked = $this->accounts->getByIdForUpdate((int) $id);
                if ($locked === null) {
                    throw new \DomainException('Account not found under lock.');
                }
                $lockedAccounts[] = $locked;
            }

            /**
             * После сортировки:
             * - $firstLocked может быть sender или receiver;
             * - нам всё равно, потому что ниже мы снова находим по owner.
             *
             * Важно не перепутать account identities.
             */
            $senderLocked = $this->accounts->findByOwnerAndCurrencyNetwork(
                $command->fromOwnerType,
                $command->fromOwnerId,
                $command->currencyNetworkId
            );

            $receiverLocked = $this->accounts->findByOwnerAndCurrencyNetwork(
                $command->toOwnerType,
                $command->toOwnerId,
                $command->currencyNetworkId
            );

            if ($senderLocked === null || $receiverLocked === null) {
                throw new \DomainException('Transfer accounts not found.');
            }

            $senderLocked = $this->accounts->getByIdForUpdate($senderLocked->id());
            $receiverLocked = $this->accounts->getByIdForUpdate($receiverLocked->id());

            if ($senderLocked === null || $receiverLocked === null) {
                throw new \DomainException('Transfer accounts could not be locked.');
            }

            $amount = new Money($command->amount);

            $senderBefore = $senderLocked->balance()->amount;
            $receiverBefore = $receiverLocked->balance()->amount;

            /**
             * Debit sender.
             */
            $senderLocked->debit($amount);

            /**
             * Credit receiver.
             */
            $receiverLocked->credit($amount);

            $senderAfter = $senderLocked->balance()->amount;
            $receiverAfter = $receiverLocked->balance()->amount;

            $senderSaved = $this->accounts->save($senderLocked);
            $receiverSaved = $this->accounts->save($receiverLocked);

            /**
             * Внутренний перевод = 2 journal entries.
             * Так audit будет честным и прозрачным.
             */
            DB::table('account_transactions')->insert([
                [
                    'ledger_operation_id' => $operation->id(),
                    'account_id' => $senderSaved->id(),
                    'currency_network_id' => $senderSaved->currencyNetworkId(),
                    'direction' => 'debit',
                    'amount' => $amount->amount,
                    'balance_before' => $senderBefore,
                    'balance_after' => $senderAfter,
                    'reference_type' => $command->referenceType,
                    'reference_id' => $command->referenceId,
                    'status' => 'confirmed',
                    'metadata' => json_encode(array_merge($command->metadata, [
                        'role' => 'sender',
                        'transfer_kind' => 'internal',
                    ]), JSON_UNESCAPED_UNICODE),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'ledger_operation_id' => $operation->id(),
                    'account_id' => $receiverSaved->id(),
                    'currency_network_id' => $receiverSaved->currencyNetworkId(),
                    'direction' => 'credit',
                    'amount' => $amount->amount,
                    'balance_before' => $receiverBefore,
                    'balance_after' => $receiverAfter,
                    'reference_type' => $command->referenceType,
                    'reference_id' => $command->referenceId,
                    'status' => 'confirmed',
                    'metadata' => json_encode(array_merge($command->metadata, [
                        'role' => 'receiver',
                        'transfer_kind' => 'internal',
                    ]), JSON_UNESCAPED_UNICODE),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            $operation->markPosted();
            $this->operations->save($operation);
        });
    }
}
