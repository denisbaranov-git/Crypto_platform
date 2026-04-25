<?php

declare(strict_types=1);

//namespace App\Jobs;
namespace App\Infrastructure\Withdrawal\Jobs;

use App\Infrastructure\Persistence\Eloquent\Models\EloquentWithdrawal;
use App\Infrastructure\Withdrawal\Jobs\BroadcastWithdrawalJob;
use App\Infrastructure\Withdrawal\Jobs\ConsumeWithdrawalHoldJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * CHANGED:
 * - recovery-only job;
 * - handles stale reserved / broadcasted / settled states;
 * - no outbox needed. //denis ???????
 */

//ReconcileStuckWithdrawalsJob
//
//reserved too long and has`t txid  → dispatch BroadcastWithdrawalJob
//broadcasted too long and has txid, but has`t consume → dispatch → dispatch ConsumeWithdrawalHoldJob
//settled too long and not confirmed → dispatch ConfirmWithdrawalJob - deprecated

final class ReconcileStuckWithdrawalsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;
    public int $timeout = 180;

    public function __construct(
        public int $networkId,
    ) {}

    public function handle(): void
    {
        $now = now();
        // How long a withdrawal may stay without progressing before we recover it.
        $staleMinutes = (int) config('withdrawal.recovery.stale_minutes', 15);

        //$reservedMinutes = (int) config('withdrawal.recovery.stale_reserved_minutes', 15);
        //$broadcastedMinutes = (int) config('withdrawal.recovery.stale_broadcasted_minutes', 15);
        //$settledMinutes = (int) config('withdrawal.recovery.stale_settled_minutes', 15);

        // 1) Broadcast was not completed in time.
        $staleToBroadcast = EloquentWithdrawal::query()
            ->where('network_id', $this->networkId)
            ->whereIn('status', ['reserved', 'broadcast_pending'])
            ->where('updated_at', '<=', $now->copy()->subMinutes($staleMinutes)) //updated_at <= now - $staleMinutes,| copy() что бы now не изменился
            ->orderBy('id')
            ->limit(100)
            ->get();

        foreach ($staleToBroadcast as $row) {
            dispatch(new BroadcastWithdrawalJob((int) $row->id))
                ->onQueue('withdrawals');
        }

        // 2) Broadcast happened, but consume/settlement may have been interrupted.
        $staleToConfirm = EloquentWithdrawal::query()
            ->where('network_id', $this->networkId)
            ->whereIn('status', ['broadcasted', 'settled'])
            ->whereNotNull('txid')
            ->where('updated_at', '<=', $now->copy()->subMinutes($staleMinutes))
            ->orderBy('id')
            ->limit(100)
            ->get();

//        EloquentWithdrawal::query()
//            ->where('network_id', $this->networkId)
//            ->where('status', 'settled')
//            ->where('updated_at', '<=', $now->copy()->subMinutes($settledMinutes))
//            ->orderBy('id')
//            ->limit(100)
//            ->get()
//            ->each(fn ($row) => dispatch(new ConfirmWithdrawalJob((int) $this->networkId)));

        if ($staleToConfirm->isNotEmpty()) {
            Log::channel('ops')->info('Stale withdrawals found for confirmation recovery', [
                'network_id' => $this->networkId,
                'count' => $staleToConfirm->count(),
            ]);
        }
    }
}
