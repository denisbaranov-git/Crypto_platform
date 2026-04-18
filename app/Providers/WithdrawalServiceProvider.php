<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Withdrawal\Handlers\BroadcastWithdrawalHandler;
use App\Application\Withdrawal\Handlers\CancelWithdrawalHandler;
use App\Application\Withdrawal\Handlers\ConsumeWithdrawalHoldHandler;
use App\Application\Withdrawal\Handlers\RequestWithdrawalHandler;
use App\Application\Withdrawal\Handlers\UpdateWithdrawalConfirmationsHandler;
use App\Contracts\WithdrawalStrategy;
use App\Domain\Ledger\Repositories\LedgerHoldRepository;
use App\Domain\Withdrawal\Repositories\WithdrawalAttemptRepository;
use App\Domain\Withdrawal\Repositories\WithdrawalRepository;
use App\Domain\Withdrawal\Services\WithdrawalConfirmationRequirementResolver;
use App\Domain\Withdrawal\Services\WithdrawalEligibilityPolicy;
use App\Domain\Withdrawal\Services\WithdrawalFeeCalculator;
use App\Domain\Withdrawal\Services\WithdrawalRoutingService;
use App\Infrastructure\Persistence\Eloquent\Ledger\Repositories\EloquentLedgerHoldRepository;
use App\Infrastructure\Persistence\Eloquent\Withdrawal\Repositories\EloquentWithdrawalAttemptRepository;
use App\Infrastructure\Persistence\Eloquent\Withdrawal\Repositories\EloquentWithdrawalRepository;
use App\Infrastructure\Withdrawal\Services\EloquentWithdrawalConfirmationRequirementResolver;
use App\Infrastructure\Withdrawal\Services\EloquentWithdrawalEligibilityPolicy;
use App\Infrastructure\Withdrawal\Services\EloquentWithdrawalFeeCalculator;
use App\Infrastructure\Withdrawal\Services\EloquentWithdrawalRoutingService;
use App\Infrastructure\Withdrawal\Services\BitcoinWithdrawalStrategy;
use App\Infrastructure\Withdrawal\Services\EvmWithdrawalStrategy;
use App\Infrastructure\Withdrawal\Services\TronWithdrawalStrategy;
use Illuminate\Support\ServiceProvider;

final class WithdrawalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(WithdrawalRepository::class, EloquentWithdrawalRepository::class);
        $this->app->bind(WithdrawalAttemptRepository::class, EloquentWithdrawalAttemptRepository::class);
        $this->app->bind(LedgerHoldRepository::class, EloquentLedgerHoldRepository::class);

        $this->app->bind(WithdrawalFeeCalculator::class, EloquentWithdrawalFeeCalculator::class);
        $this->app->bind(WithdrawalEligibilityPolicy::class, EloquentWithdrawalEligibilityPolicy::class);
        $this->app->bind(WithdrawalConfirmationRequirementResolver::class, EloquentWithdrawalConfirmationRequirementResolver::class);
        $this->app->bind(WithdrawalRoutingService::class, EloquentWithdrawalRoutingService::class);

        $this->app->tag([
            EvmWithdrawalStrategy::class,
            TronWithdrawalStrategy::class,
            BitcoinWithdrawalStrategy::class,
        ], 'withdrawal.strategies');

        $this->app->singleton(BroadcastWithdrawalHandler::class, function ($app): BroadcastWithdrawalHandler {
            return new BroadcastWithdrawalHandler(
                withdrawals: $app->make(WithdrawalRepository::class),
                attempts: $app->make(WithdrawalAttemptRepository::class),
                routing: $app->make(WithdrawalRoutingService::class),
                ledger: $app->make(\App\Infrastructure\Ledger\Services\EloquentLedgerService::class),
                strategies: $app->tagged('withdrawal.strategies'),
            );
        });

        $this->app->singleton(RequestWithdrawalHandler::class);
        $this->app->singleton(CancelWithdrawalHandler::class);
        $this->app->singleton(ConsumeWithdrawalHoldHandler::class);
        $this->app->singleton(UpdateWithdrawalConfirmationsHandler::class);
    }
}
