<?php

declare(strict_types=1);

namespace App\Infrastructure\Outbox\Jobs;

use App\Application\Deposit\Commands\CreditDepositCommand;
use App\Application\Deposit\Commands\ReverseDepositCreditCommand;
use App\Application\Deposit\Handlers\CreditDepositHandler;
use App\Application\Deposit\Handlers\ReverseDepositCreditHandler;
use App\Domain\Deposit\Events\DepositConfirmed;
//use App\Infrastructure\Outbox\Repositories\OutboxRepository;
use App\Domain\Deposit\Events\DepositReorged;
use App\Domain\Shared\Outbox\OutboxRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Throwable;

final class OutboxRelayJob implements ShouldQueue
{
    public function __construct(
        public int $batchSize = 100
    ) {}

    public function handle(
        OutboxRepository $outbox,
        CreditDepositHandler $creditDepositHandler,
        ReverseDepositCreditHandler $reverseDepositCreditHandler,
    ): void {
        /**
         * Возвращаем зависшие processing rows обратно в pending.
         */
        $outbox->reclaimStaleProcessing();

        $messages = $outbox->fetchPending($this->batchSize);

        foreach ($messages as $message) {
            try {
                $payload = $message->payload;

                if ($message->event_type === DepositConfirmed::class) {
                    $depositId = (int) ($payload['depositId'] ?? 0);
                    $networkId = (int) ($payload['networkId'] ?? 0);
                    $externalKey = (string) ($payload['externalKey'] ?? '');

                    if ($depositId <= 0 || $networkId <= 0 || $externalKey === '') {
                        throw new \DomainException('Invalid DepositConfirmed payload.');
                    }

                    $operationId = 'deposit-credit:' . $networkId . ':' . $externalKey;

                    $creditDepositHandler->handle(new CreditDepositCommand(
                        depositId: $depositId,
                        operationId: $operationId,
                        metadata: $payload
                    ));
                } elseif ($message->event_type === DepositReorged::class) {
                    $depositId = (int) ($payload['depositId'] ?? 0);

                    if ($depositId <= 0) {
                        throw new \DomainException('Invalid DepositReorged payload.');
                    }

                    $reverseDepositCreditHandler->handle(new ReverseDepositCreditCommand(
                        depositId: $depositId,
                        metadata: $payload
                    ));
                } else {
                    throw new \DomainException('Unknown outbox event type: ' . $message->event_type);
                }

                $outbox->markDispatched($message->idempotency_key);
            } catch (Throwable $e) {
                if ($this->isPermanentFailure($e)) {
                    $outbox->markTerminalFailure(
                        $message->idempotency_key,
                        $e->getMessage()
                    );

                    logger()->critical('Outbox terminal failure', [
                        'idempotency_key' => $message->idempotency_key,
                        'event_type' => $message->event_type,
                        'error' => $e->getMessage(),
                    ]);

                    continue;
                }

                $delaySeconds = $this->backoffSeconds((int) $message->attempts);

                $outbox->markRetryableFailure(
                    $message->idempotency_key,
                    $e->getMessage(),
                    $delaySeconds
                );
            }
        }
    }

    private function isPermanentFailure(Throwable $e): bool
    {
        return $e instanceof \DomainException;
    }

    private function backoffSeconds(int $attempts): int
    {
        return match (true) {
            $attempts <= 1 => 60,
            $attempts <= 3 => 300,
            $attempts <= 5 => 900,
            default => 3600,
        };
    }
}
