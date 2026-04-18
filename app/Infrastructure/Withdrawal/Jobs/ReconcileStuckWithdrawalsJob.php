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
 * - no outbox needed.
 */
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
        $staleReserveMinutes = (int) config('withdrawal.recovery.stale_reserve_minutes', 10);
        $staleBroadcastMinutes = (int) config('withdrawal.recovery.stale_broadcast_minutes', 10);
        $staleSettlementMinutes = (int) config('withdrawal.recovery.stale_settlement_minutes', 10);

        $now = now();

        $reserved = EloquentWithdrawal::query()
            ->where('network_id', $this->networkId)
            ->where('status', 'reserved')
            ->where('updated_at', '<=', $now->copy()->subMinutes($staleReserveMinutes))
            ->orderBy('id')
            ->limit(100)
            ->get();

        foreach ($reserved as $row) {
            dispatch(new BroadcastWithdrawalJob((int) $row->id));
        }

        $broadcasted = EloquentWithdrawal::query()
            ->where('network_id', $this->networkId)
            ->where('status', 'broadcasted')
            ->where('updated_at', '<=', $now->copy()->subMinutes($staleBroadcastMinutes))
            ->orderBy('id')
            ->limit(100)
            ->get();

        foreach ($broadcasted as $row) {
            dispatch(new ConsumeWithdrawalHoldJob((int) $row->id));
        }

        $settled = EloquentWithdrawal::query()
            ->where('network_id', $this->networkId)
            ->where('status', 'settled')
            ->where('updated_at', '<=', $now->copy()->subMinutes($staleSettlementMinutes))
            ->orderBy('id')
            ->limit(100)
            ->get();

        foreach ($settled as $row) {
            dispatch(new ConfirmWithdrawalJob((int) $this->networkId));
            break;
        }
    }
}
