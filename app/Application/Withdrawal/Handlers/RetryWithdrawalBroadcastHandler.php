<?php

declare(strict_types=1);

namespace App\Application\Withdrawal\Handlers;

use App\Application\Withdrawal\Commands\BroadcastWithdrawalCommand;
use App\Application\Withdrawal\Commands\RetryWithdrawalBroadcastCommand;

final class RetryWithdrawalBroadcastHandler
{
    public function __construct(
        private readonly BroadcastWithdrawalHandler $broadcastWithdrawalHandler,
    ) {}

    public function handle(RetryWithdrawalBroadcastCommand $command): void
    {
        $this->broadcastWithdrawalHandler->handle(
            new BroadcastWithdrawalCommand(
                withdrawalId: $command->withdrawalId,
                operationId: 'withdrawal:' . $command->withdrawalId . ':broadcast',
                metadata: $command->metadata
            )
        );
    }
}
