<?php

namespace App\Application\Deposit\Handlers;

use App\Application\Deposit\Commands\ConfirmDepositCommand;
use App\Application\Deposit\Commands\UpdateDepositConfirmationsCommand;
use App\Domain\Deposit\Policies\CanBeCreditedPolicy;
use App\Domain\Deposit\Repositories\DepositRepository;
use App\Domain\Deposit\Services\ConfirmationRequirementResolver;
use App\Domain\Deposit\ValueObjects\DepositStatus;
use App\Domain\Shared\ValueObjects\BlockNumber;
use App\Domain\Shared\ValueObjects\ExternalKey;
use Illuminate\Support\Facades\DB;

final class UpdateDepositConfirmationsHandler
{
    public function __construct(
        private readonly DepositRepository $depositsRepo,
        private readonly ConfirmationRequirementResolver $confirmationRequirement,
        private readonly CanBeCreditedPolicy $canBeCreditedPolicy,
        private readonly ConfirmDepositHandler $confirmDepositHandler,
    ) {}

    public function handle(UpdateDepositConfirmationsCommand $command): void
    {
        DB::transaction(function () use ($command): void {
            $deposit = $this->depositsRepo->findByExternalKey(
                $command->networkId,
                new ExternalKey($command->externalKey)
            );

            if (! $deposit) {
                return;
            }

            $deposit->updateConfirmations(
                blockHash: $command->blockHash,
                blockNumber: $command->blockNumber !== null ? new BlockNumber($command->blockNumber) : null,
                confirmations: $command->confirmations,
                finalizedAt: $command->finalized === true ? new \DateTimeImmutable() : null,
                metadata: $command->metadata,
            );
            if($deposit->status() === DepositStatus::Detected)
                $deposit->markPending();

            $deposit = $this->depositsRepo->save($deposit);

            $requirement = $this->confirmationRequirement->resolve(
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
