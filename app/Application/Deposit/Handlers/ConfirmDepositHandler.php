<?php

declare(strict_types=1);

namespace App\Application\Deposit\Handlers;

use App\Application\Deposit\Commands\ConfirmDepositCommand;
use App\Domain\Deposit\Events\DepositConfirmed;
use App\Domain\Deposit\ValueObjects\DepositId;
use App\Domain\Deposit\Repositories\DepositRepository;
//use App\Infrastructure\Outbox\Repositories\OutboxRepository;
use App\Domain\Deposit\ValueObjects\DepositStatus;
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
            $deposit = $this->deposits->findById(DepositId::fromString($command->depositId));

            if (! $deposit) {
                throw new \RuntimeException("Deposit {$command->depositId} not found.");
            }

            if ($deposit->status() === DepositStatus::Confirmed  || $deposit->status() === DepositStatus::Credited ) {
                return;
            }

            if ( $deposit->status() !== DepositStatus::Detected) {
                throw new \RuntimeException('Deposit can be confirmed only from detected state.');
            }

            $deposit->markConfirmed();
            $deposit = $this->deposits->save($deposit);

            foreach ($deposit->pullDomainEvents() as $event) {
                if (! $event instanceof DepositConfirmed) {
                    continue;
                }

                $this->outbox->append(
                    idempotencyKey: 'deposit:' . $deposit->id()->value() . ':confirmed',
                    aggregateType: 'deposit',
                    aggregateId: (string) $deposit->id()->value(),
                    eventType: DepositConfirmed::class,
                    payload: [
                        'depositId' => $deposit->id()->value(),
                        'networkId' => $deposit->networkId(),
                        'externalKey' => $deposit->externalKey(),
                        'blockNumber' => $deposit->blockNumber()?->value(),
                        'txid' => $deposit->txid(),
                    ]
                );
            }
        });
    }
}
