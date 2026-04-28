<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Ledger\Contracts\LedgerPostingService;
use App\Domain\Ledger\Contracts\LedgerService;
use App\Domain\Ledger\Contracts\SystemAccountResolverInterface;
use App\Infrastructure\Ledger\Services\EloquentLedgerPostingService;
use App\Infrastructure\Ledger\Services\EloquentLedgerService;
use App\Infrastructure\Ledger\Services\EloquentSystemAccountResolver;
use Illuminate\Support\ServiceProvider;

final class LedgerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LedgerService::class, EloquentLedgerService::class);
        $this->app->bind(LedgerPostingService::class, EloquentLedgerPostingService::class);
        $this->app->bind(SystemAccountResolverInterface::class, EloquentSystemAccountResolver::class);
//        $this->app->bind(WithdrawalRepository::class, EloquentWithdrawalRepository::class);
//        $this->app->singleton(RequestWithdrawalHandler::class);
//        $this->app->singleton(BroadcastWithdrawalJob::class, fn () => new BroadcastWithdrawalJob(0));
    }

    public function boot(): void
    {}
}
