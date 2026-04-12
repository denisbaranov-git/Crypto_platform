<?php

declare(strict_types=1);

namespace App\Infrastructure\Blockchain\Jobs;

use App\Domain\Deposit\Events\DepositReorged;
//use App\Infrastructure\Outbox\Repositories\OutboxRepository;
use App\Domain\Shared\Outbox\OutboxRepository;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentDeposit;
use Illuminate\Contracts\Queue\ShouldQueue;

final class ReorgRecoveryJob implements ShouldQueue
{
    /*
     *
     * Это safety job.
       Он нужен, чтобы восстановить outbox-сообщения, если detector успел пометить deposit как reorged, но событие по какой-то причине не было доставлено дальше.
       Запускать в ручном режиме
     */
    public function __construct(
        public int $networkId,
        public int $rewindToBlock,
        public int $oldLastProcessedBlock,
    ) {}

    public function handle(OutboxRepository $outbox): void
    {
        $deposits = EloquentDeposit::query()
            ->where('network_id', $this->networkId)
            ->where('status', 'reorged') //status = reorged
            ->whereNotNull('credited_operation_id')
            ->whereNull('reversal_operation_id') //Reason: reversal_operation_id is null - it is meaning reversal is NOT completed!!!
            ->whereBetween('block_number', [$this->rewindToBlock + 1, $this->oldLastProcessedBlock])
            ->orderBy('id')
            ->get();

        foreach ($deposits as $deposit) {
            $idempotencyKey = 'deposit:' . $deposit->id . ':reorged:' . $this->rewindToBlock;

            $outbox->append(
                idempotencyKey: $idempotencyKey,
                aggregateType: 'deposit',
                aggregateId: (string) $deposit->id,
                eventType: DepositReorged::class,
                payload: [
                    'depositId' => $deposit->id,
                    'networkId' => $deposit->network_id,
                    'externalKey' => $deposit->external_key,
                    'rewindToBlock' => $this->rewindToBlock,
                    'oldLastProcessedBlock' => $this->oldLastProcessedBlock,
                    'creditedOperationId' => $deposit->credited_operation_id,
                ]
            );
        }
    }
}
