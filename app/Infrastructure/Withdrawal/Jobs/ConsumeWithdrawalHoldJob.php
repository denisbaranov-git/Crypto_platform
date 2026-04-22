<?php

declare(strict_types=1);

//namespace App\Jobs;
namespace App\Infrastructure\Withdrawal\Jobs;

use App\Application\Withdrawal\Commands\ConsumeWithdrawalHoldCommand;
use App\Application\Withdrawal\Handlers\ConsumeWithdrawalHoldHandler;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * ConsumeWithdrawalHoldJob
 *
 * if  Case A: broadcast succeeded, consume failed
 * Withdrawal:
 * status = broadcasted
 * txid != null
 * consume_operation_id = null
 *
 * Recovery job:
 * dispatch ConsumeWithdrawalHoldJob
 * --------------------------------------------------
 */
final class ConsumeWithdrawalHoldJob implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Queueable;

    public int $tries = 5;
    public int $timeout = 180;

    public function __construct(
        public int $withdrawalId,
    ) {}

    public function uniqueId(): string
    {
        return 'withdrawal:' . $this->withdrawalId . ':consume';
    }

    public function handle(ConsumeWithdrawalHoldHandler $handler): void
    {
        $handler->handle(new ConsumeWithdrawalHoldCommand(
            withdrawalId: $this->withdrawalId,
            metadata: [
                'source' => 'consume_withdrawal_hold_job',
            ]
        ));
    }
}
