<?php

namespace App\Application\Deposit\Handlers;

use App\Application\Deposit\Commands\ConfirmDepositCommand;
use App\Application\Deposit\Commands\UpdateDepositConfirmationsCommand;
use App\Domain\Deposit\Policies\CanBeCreditedPolicy;
use App\Domain\Deposit\Repositories\DepositRepository;
use App\Domain\Deposit\Services\ConfirmationRequirementResolver;
use App\Domain\Deposit\ValueObjects\BlockNumber;
use App\Domain\Deposit\ValueObjects\ExternalKey;
use Illuminate\Support\Facades\DB;

final class UpdateDepositConfirmationsHandler
{
    public function __construct(
        private readonly DepositRepository $deposits,
        private readonly ConfirmationRequirementResolver $resolver,
        private readonly CanBeCreditedPolicy $canBeCreditedPolicy,
        private readonly ConfirmDepositHandler $confirmDepositHandler,
    ) {}

    public function handle(UpdateDepositConfirmationsCommand $command): void
    {
        DB::transaction(function () use ($command): void {
            $deposit = $this->deposits->findByExternalKey(
                $command->networkId,
                new ExternalKey($command->externalKey)
            );

            if (! $deposit) {
                return;
            }

            $deposit->syncEvidence(
                fromAddress: $command->fromAddress,
                toAddress: $command->toAddress,
                blockHash: $command->blockHash,
                blockNumber: $command->blockNumber !== null ? new BlockNumber($command->blockNumber) : null,
                confirmations: $command->confirmations,
                finalizedAt: $command->finalized === true ? new \DateTimeImmutable() : null,
                metadata: $command->metadata,
            );

            $deposit = $this->deposits->save($deposit);

            $requirement = $this->resolver->resolve(
                $deposit->currencyNetworkId(),
                $deposit->amount()
            );

            if ($this->canBeCreditedPolicy->canBeCredited($deposit, $requirement)) {
                $this->confirmDepositHandler->handle(
                    new ConfirmDepositCommand($deposit->id()->value())
                );
            }
        });
    }
}
