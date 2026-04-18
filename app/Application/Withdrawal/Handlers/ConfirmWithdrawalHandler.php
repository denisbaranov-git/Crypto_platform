<?php

declare(strict_types=1);

namespace App\Application\Withdrawal\Handlers;

use App\Application\Withdrawal\Commands\ConfirmWithdrawalCommand;
use App\Domain\Shared\Outbox\OutboxRepository;
use App\Domain\Withdrawal\Events\WithdrawalConfirmed;
use App\Domain\Withdrawal\Repositories\WithdrawalRepository;
use App\Domain\Withdrawal\Services\WithdrawalConfirmationRequirementResolver;
use App\Domain\Withdrawal\ValueObjects\WithdrawalStatus;
use DomainException;
use Illuminate\Support\Facades\DB;

final class ConfirmWithdrawalHandler
{
    public function __construct(
        private readonly WithdrawalRepository $withdrawalsRepo,
        //private readonly WithdrawalConfirmationRequirementResolver $requirements,
        private readonly OutboxRepository $outbox,
    ) {}

    public function handle(ConfirmWithdrawalCommand $command): void
    {
        DB::transaction(function () use ($command): void {
            $withdrawal = $this->withdrawalsRepo->lockById($command->withdrawalId);

            if (! $withdrawal) {
                throw new DomainException('Withdrawal not found.');
            }
//
//            $required = $this->requirements->resolve($withdrawal->currencyNetworkId(), $withdrawal->amount()->value());
//
//            if ($command->confirmations < $required) {
//                return;
//            }
            if ( $withdrawal->status()  !== WithdrawalStatus::SETTLED) { /// denis проверить установку статуса!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
                throw new \RuntimeException('Withdrawal can be confirmed only from settled state.');
            }
            $withdrawal->markConfirmed();

            $this->withdrawalsRepo->save($withdrawal);

            foreach ($withdrawal->pullDomainEvents() as $event) {
                if (!$event instanceof WithdrawalConfirmed) {
                    continue;
                }

                $this->outbox->append(
                    idempotencyKey: 'withdrawal:' . $withdrawal->id()->value() . ':confirmed',
                    aggregateType: 'withdrawal',
                    aggregateId: (string)$withdrawal->id()->value(),
                    eventType: WithdrawalConfirmed::class,
                    payload: [
                        'withdrawalId' => $withdrawal->id()->value(),
                        'networkId' => $withdrawal->networkId(),

                        'externalKey' => $withdrawal->externalKey(), //denis// externalKey!!!!!!!!!!!!!!!!!!!!!!!!!!!
                        'blockNumber' => $withdrawal->blockNumber()?->value(),
                        'txid' => $withdrawal->txid(),
                    ]
                );
            }
        });
    }
}
