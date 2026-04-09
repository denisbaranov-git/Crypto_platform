<?php

namespace App\Providers;

use App\Domain\Deposit\Repositories\DepositRepository;
use App\Domain\Deposit\Services\ConfirmationRequirementResolver;
use App\Domain\Deposit\Services\CurrencyNetworkQueryService;
use App\Domain\Shared\Outbox\OutboxRepository;
use App\Infrastructure\Deposit\Services\EloquentConfirmationRequirementResolver;
use App\Infrastructure\Deposit\Services\EloquentCurrencyNetworkQueryService;
use App\Infrastructure\Persistence\Eloquent\Deposit\Repositories\EloquentDepositRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentOutboxRepository;
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
