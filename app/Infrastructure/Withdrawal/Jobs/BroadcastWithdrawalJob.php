<?php

declare(strict_types=1);

//namespace App\Jobs;
namespace App\Infrastructure\Withdrawal\Jobs;

use App\Application\Withdrawal\Commands\BroadcastWithdrawalCommand;
use App\Application\Withdrawal\Handlers\BroadcastWithdrawalHandler;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * CHANGED:
 * - this replaces the old outbox-driven internal step;
 * - dispatch it after the request transaction commits.
 * ************************************************
 * Request
 *
 * POST /api/withdrawals
 *
 * request validation
 * VO validation
 * business policy validation
 * fee rule selection
 * fee amount calculation
 * total debit calculation
 * withdrawal creation
 * reserveFunds()
 * ledger_hold_id binding
 * withdrawal status becomes reserved
 */

final class BroadcastWithdrawalJob implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 180;

    public function __construct(
        public int $withdrawalId,
    ) {}

    public function uniqueId(): string
    {
        return 'withdrawal:' . $this->withdrawalId . ':broadcast';
    }

    public function handle(BroadcastWithdrawalHandler $handler): void
    {
        $handler->handle(new BroadcastWithdrawalCommand(
            withdrawalId: $this->withdrawalId,
            metadata: [
                'source' => 'broadcast_withdrawal_job',
            ]
        ));
    }
}
