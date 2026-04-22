<?php

declare(strict_types=1);

//namespace App\Jobs;
namespace App\Infrastructure\Withdrawal\Jobs;

use App\Infrastructure\Persistence\Eloquent\Models\EloquentWithdrawal;
use App\Infrastructure\Withdrawal\Jobs\BroadcastWithdrawalJob;
use App\Infrastructure\Withdrawal\Jobs\ConsumeWithdrawalHoldJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

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
//settled too long and not confirmed → dispatch ConfirmWithdrawalJob

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

        $reservedMinutes = (int) config('withdrawal.recovery.stale_reserved_minutes', 15);
        $broadcastedMinutes = (int) config('withdrawal.recovery.stale_broadcasted_minutes', 15);
        $settledMinutes = (int) config('withdrawal.recovery.stale_settled_minutes', 15);

        EloquentWithdrawal::query()
            ->where('network_id', $this->networkId)
            ->where('status', 'reserved')
            ->where('updated_at', '<=', $now->copy()->subMinutes($reservedMinutes))
            ->orderBy('id')
            ->limit(100)
            ->get()
            ->each(fn ($row) => dispatch(new BroadcastWithdrawalJob((int) $row->id)));

        EloquentWithdrawal::query()
            ->where('network_id', $this->networkId)
            ->where('status', 'broadcasted')
            ->where('updated_at', '<=', $now->copy()->subMinutes($broadcastedMinutes))
            ->orderBy('id')
            ->limit(100)
            ->get()
            ->each(fn ($row) => dispatch(new ConsumeWithdrawalHoldJob((int) $row->id)));

        EloquentWithdrawal::query()
            ->where('network_id', $this->networkId)
            ->where('status', 'settled')
            ->where('updated_at', '<=', $now->copy()->subMinutes($settledMinutes))
            ->orderBy('id')
            ->limit(100)
            ->get()
            ->each(fn ($row) => dispatch(new ConfirmWithdrawalJob((int) $this->networkId)));
    }
}
