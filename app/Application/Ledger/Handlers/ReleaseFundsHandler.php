<?php

declare(strict_types=1);

namespace App\Application\Ledger\Handlers;

use App\Application\Ledger\Commands\ReleaseFundsCommand;
use App\Domain\Ledger\Repositories\AccountRepository;
use App\Domain\Ledger\Repositories\LedgerHoldRepository;
use App\Domain\Ledger\Repositories\LedgerOperationRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * ReleaseFundsHandler
 *
 * Почему отдельный handler:
 * - release не равен consume;
 * - release не должен уменьшать balance;
 * - release возвращает только availability.
 */
final class ReleaseFundsHandler
{
    public function __construct(
        private readonly AccountRepository $accounts,
        private readonly LedgerOperationRepository $operations,
        private readonly LedgerHoldRepository $holds,
    ) {}

    public function handle(ReleaseFundsCommand $command): void
    {
        DB::transaction(function () use ($command) {
            $existingOperation = $this->operations->findByIdempotencyKey($command->idempotencyKey);

            if ($existingOperation !== null && $existingOperation->status() === 'posted') {
                return;
            }

            $operation = $existingOperation ?? new \App\Domain\Ledger\Entities\LedgerOperation(
                id: (string) Str::uuid(),
                idempotencyKey: $command->idempotencyKey,
                type: 'release',
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
                throw new \DomainException('Only active hold can be released.');
            }

            $account = $this->accounts->getByIdForUpdate($hold->accountId());

            if ($account === null) {
                throw new \DomainException('Account not found for hold release.');
            }

            $amount = new \App\Domain\Ledger\ValueObjects\Money($hold->amount());

            /**
             * Возвращаем доступность.
             * balance не меняется.
             */
            $account->releaseReservation($amount);
            $this->accounts->save($account);

            $hold->release();
            $this->holds->save($hold);

            $operation->markPosted();
            $this->operations->save($operation);
        });
    }
}
