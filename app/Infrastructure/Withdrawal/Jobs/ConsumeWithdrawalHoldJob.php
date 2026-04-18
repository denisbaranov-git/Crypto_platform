<?php

declare(strict_types=1);

//namespace App\Jobs;
namespace App\Infrastructure\Withdrawal\Jobs;

use App\Application\Withdrawal\Commands\ConsumeWithdrawalHoldCommand;
use App\Application\Withdrawal\Handlers\ConsumeWithdrawalHoldHandler;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class ConsumeWithdrawalHoldJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;
    public int $timeout = 180;

    public function __construct(
        public int $withdrawalId,
    ) {}

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
