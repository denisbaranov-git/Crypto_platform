<?php

declare(strict_types=1);

//namespace App\Jobs;
namespace App\Infrastructure\Withdrawal\Jobs;

use App\Application\Withdrawal\Commands\BroadcastWithdrawalCommand;
use App\Application\Withdrawal\Handlers\BroadcastWithdrawalHandler;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * CHANGED:
 * - this replaces the old outbox-driven internal step;
 * - dispatch it after the request transaction commits.
 */
final class BroadcastWithdrawalJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 180;

    public function __construct(
        public int $withdrawalId,
    ) {}

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
