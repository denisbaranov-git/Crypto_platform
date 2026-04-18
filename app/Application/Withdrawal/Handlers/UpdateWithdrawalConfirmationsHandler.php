<?php

declare(strict_types=1);

namespace App\Application\Withdrawal\Handlers;

use App\Application\Withdrawal\Commands\ConfirmWithdrawalCommand;
use App\Application\Withdrawal\Commands\UpdateWithdrawalConfirmationsCommand;
use App\Domain\Withdrawal\Repositories\WithdrawalRepository;
use App\Domain\Withdrawal\Services\WithdrawalConfirmationRequirementResolver;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * CHANGED:
 * - confirmation is operational/read-model status;
 * - it does not move money;
 * - it only flips withdrawal to confirmed after required depth/finality.
 */
final class UpdateWithdrawalConfirmationsHandler
{
    public function __construct(
        private readonly WithdrawalRepository $withdrawalsRepo,
        private readonly WithdrawalConfirmationRequirementResolver $requirements,
        //private readonly CanBeDebitedPolicy $canBeDebitedPolicy,
        private readonly ConfirmWithdrawalHandler $confirmWithdrawalHandler,
    ) {}

    public function handle(UpdateWithdrawalConfirmationsCommand $command): void
    {
        DB::transaction(function () use ($command): void {
            $withdrawal = $this->withdrawalsRepo->lockById($command->withdrawalId);

            if (! $withdrawal) {
                throw new DomainException('Withdrawal not found.');
            }

            if ($withdrawal->txid() === null || $withdrawal->txid()->value() !== $command->txid) {
                return;
            }

            if (in_array($withdrawal->status(), ['confirmed', 'settled', 'debited'], true)) {
                return;
            }

            $withdrawal->updateConfirmations(
                        $command->blockHash,
                        $command->blockNumber,
                        $command->confirmations,
                        null,
                        $command->metadata );

            $this->withdrawalsRepo->save($withdrawal);

            $requirement = $this->requirements->resolve(
                $command->currencyNetworkId,
                $withdrawal->amount()->value()
            );

            if (! $command->finalized && $command->confirmations < $requirement) {
                return;
            }
            $this->confirmWithdrawalHandler->handle(
                new ConfirmWithdrawalCommand($withdrawal->id()->value())
            );
//            if ($this->canBeDebitedPolicy->canBeDebited($withdrawal, $requirement)) {
//                $this->confirmWithdrawalHandler->handle(
//                    new ConfirmWithdrawalCommand($withdrawal->id()->value())
//                );
//            }
            //$withdrawal->markConfirmed();
        });
    }
}
