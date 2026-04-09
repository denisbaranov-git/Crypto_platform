<?php

namespace App\Infrastructure\Persistence\Eloquent\Jobs;
//namespace App\Infrastructure\Outbox;

use App\Application\Deposit\Commands\CreditDepositCommand;
use App\Application\Deposit\Handlers\CreditDepositHandler;
use App\Domain\Shared\Outbox\OutboxRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Throwable;

final class OutboxRelayJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $batchSize = 100
    ) {}

    public function handle(OutboxRepository $outbox): void
    {
        $messages = $outbox->fetchPending($this->batchSize);

        foreach ($messages as $message) {
            try {
                if ($message->event_type === \App\Domain\Deposit\Events\DepositConfirmed::class) {
                    $payload = $message->payload;

                    // Событие подтверждения превращаем в команду кредитования.
                    // Важно: downstream должен быть идемпотентным.
                    app(CreditDepositHandler::class)->handle(
                        new CreditDepositCommand(
                            depositId: (int) $payload['depositId'],
                            operationId: 'deposit-credit:' . $payload['networkId'] . ':' . $payload['externalKey'],
                            metadata: $payload
                        )
                    );
                }

                $outbox->markDispatched($message->idempotency_key);
            } catch (Throwable $e) {
                $outbox->markFailed($message->idempotency_key, $e->getMessage());
            }
        }
    }
}
