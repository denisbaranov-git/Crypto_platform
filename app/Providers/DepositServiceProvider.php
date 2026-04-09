<?php

namespace App\Providers;

use App\Domain\Deposit\Services\CurrencyNetworkQueryService;
use App\Domain\Deposit\Repositories\DepositRepository;
use App\Domain\Deposit\Services\ConfirmationRequirementResolver;
use App\Domain\Ledger\Contracts\LedgerService;
use App\Domain\Shared\Outbox\OutboxRepository;
use App\Infrastructure\Deposit\Services\EloquentCurrencyNetworkQueryService;
use App\Infrastructure\Deposit\Services\EloquentConfirmationRequirementResolver;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentOutboxRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentDepositRepository;
use Illuminate\Support\ServiceProvider;

final class DepositServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DepositRepository::class, EloquentDepositRepository::class);
        $this->app->bind(ConfirmationRequirementResolver::class, EloquentConfirmationRequirementResolver::class);
        $this->app->bind(OutboxRepository::class, EloquentOutboxRepository::class);
        $this->app->bind(CurrencyNetworkQueryService::class, EloquentCurrencyNetworkQueryService::class);

        // LedgerService
    }
}
