<?php

namespace App\Infrastructure\Outbox\Jobs;

use App\Application\Deposit\Commands\CreditDepositCommand;
use App\Application\Deposit\Handlers\CreditDepositHandler;
use App\Domain\Deposit\Events\DepositConfirmed;
use App\Domain\Shared\Outbox\OutboxRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Throwable;

final class OutboxRelayJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;
    public int $timeout = 120;

    public function __construct(
        public int $batchSize = 100
    ) {}

    public function handle(
        OutboxRepository $outbox,
        CreditDepositHandler $creditDepositHandler,
    ): void {
        $messages = $outbox->fetchPending($this->batchSize);

        foreach ($messages as $message) {
            try {
                if ($message->event_type === DepositConfirmed::class) {
                    $payload = $message->payload;

                    $depositId = (int) ($payload['depositId'] ?? 0);
                    $networkId = (int) ($payload['networkId'] ?? 0);
                    $externalKey = (string) ($payload['externalKey'] ?? '');

                    $operationId = 'deposit-credit:' . $networkId . ':' . $externalKey;

                    $creditDepositHandler->handle(new CreditDepositCommand(
                        depositId: $depositId,
                        operationId: $operationId,
                        metadata: $payload
                    ));
                }

                $outbox->markDispatched($message->idempotency_key);
            } catch (Throwable $e) {
                $outbox->markFailed($message->idempotency_key, $e->getMessage());
            }
        }
    }
}
