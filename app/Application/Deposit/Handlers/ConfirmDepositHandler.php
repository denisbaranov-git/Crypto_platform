<?php

namespace App\Application\Deposit\Handlers;

use App\Application\Deposit\Commands\ConfirmDepositCommand;
use App\Domain\Deposit\Events\DepositConfirmed;
use App\Domain\Deposit\Repositories\DepositRepository;
use App\Domain\Deposit\ValueObjects\DepositId;
use App\Domain\Deposit\ValueObjects\DepositStatus;
use App\Domain\Shared\Outbox\OutboxMessage;
use App\Domain\Shared\Outbox\OutboxRepository;
use Illuminate\Support\Facades\DB;

final class ConfirmDepositHandler
{
    public function __construct(
        private readonly DepositRepository $deposits,
        private readonly OutboxRepository $outbox,
    ) {}

    public function handle(ConfirmDepositCommand $command): void
    {
        DB::transaction(function () use ($command): void {
            $deposit = $this->deposits->findById(new DepositId($command->depositId));

            if (! $deposit) {
                throw new \RuntimeException("Deposit {$command->depositId} not found.");
            }

            if ($deposit->status() === DepositStatus::Confirmed || $deposit->status() === DepositStatus::Credited) {
                return;
            }

            $deposit->markConfirmed();
            $deposit = $this->deposits->save($deposit);


            foreach ($deposit->pullDomainEvents() as $event) {
                if (! $event instanceof DepositConfirmed) {
                    continue;
                }
                $this->outbox->append(OutboxMessage::fromDomainEvent(
                    aggregateType: 'deposit',
                    aggregateId: $deposit->id()->value(),
                    event: $event,
                    idempotencyKey: 'deposit:' . $deposit->id()->value() . ':confirmed',
                ));
            }
        });
    }
}
