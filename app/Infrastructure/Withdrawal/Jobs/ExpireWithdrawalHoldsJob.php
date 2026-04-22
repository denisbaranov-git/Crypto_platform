<?php

namespace App\Infrastructure\Withdrawal\Jobs;

use App\Application\Withdrawal\Commands\CancelWithdrawalCommand;
use App\Application\Withdrawal\Handlers\CancelWithdrawalHandler;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentLedgerHold;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentWithdrawal;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * ledger_hold.expires_at прошёл, а withdrawal ещё не broadcasted / settled :
 *
 * освободить hold,
 * снять reserved_balance,
 * пометить withdrawal cancelled или failed,
 * больше не пытаться broadcast’ить этот withdrawal.
 *
 * ExpireWithdrawalHoldsJob должен проверять:
 * * если txid уже есть — никаких cancel/release
 * если withdrawal ещё только reserved и tx нет — можно release/cancel
*/
final class ExpireWithdrawalHoldsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;
    public int $timeout = 120;

    public function handle(CancelWithdrawalHandler $cancelHandler): void
    {
        $now = now();

        $expiredHolds = EloquentLedgerHold::query()
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now)
            ->orderBy('id')
            ->limit(200)
            ->get();

        foreach ($expiredHolds as $hold) {
            $withdrawal = EloquentWithdrawal::query()
                ->where('ledger_hold_id', $hold->id)
                ->first();

            if (! $withdrawal) {
                continue;
            }

//            if (! in_array($withdrawal->status, ['reserved', 'broadcast_pending'], true)) {
//                continue;
//            }
            if ($withdrawal->txid !== null) {
                continue;
            }

            if (! in_array($withdrawal->status, ['reserved', 'broadcast_pending'], true)) {
                continue;
            }

            $cancelHandler->handle(new CancelWithdrawalCommand(
                withdrawalId: (int) $withdrawal->id,
                reason: 'hold_expired',
                metadata: [
                    'source' => 'expire_withdrawal_holds_job',
                    'hold_id' => $hold->id,
                ]
            ));
        }
    }
}
