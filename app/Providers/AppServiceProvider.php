<?php

namespace App\Providers;

use App\Domain\Deposit\Repositories\DepositRepository;
use App\Domain\Deposit\Services\ConfirmationRequirementResolver;
use App\Domain\Deposit\Services\DepositUniquenessChecker;
use App\Domain\Identity\Repositories\UserRepository;
use App\Domain\Shared\EventPublisher;
use App\Infrastructure\Auth\Contracts\AuthUserProvider;
use App\Infrastructure\Auth\EloquentAuthUserProvider;
use App\Infrastructure\Events\LaravelEventPublisher;
use App\Infrastructure\Persistence\Eloquent\Deposit\Repositories\EloquentDepositRepository;
use App\Infrastructure\Persistence\Eloquent\Deposit\Repositories\EloquentDepositUniquenessChecker;
use App\Infrastructure\Persistence\Eloquent\Deposit\Services\EloquentConfirmationRequirementResolver;
use App\Infrastructure\Persistence\Eloquent\Mappers\DepositMapper;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentUserRepository;
use App\Services\Wallet\AddressGenerator;
use App\Services\Wallet\AddressGeneratorInterface;
use App\Services\Wallet\HDAddressGenerator;
use App\Services\Wallet\HDAddressGeneratorInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(UserRepository::class, EloquentUserRepository::class);

        $this->app->bind(AddressGeneratorInterface::class, function ($app) {
            return new AddressGenerator();
        });

        $this->app->bind(EventPublisher::class, function ($app) {
            return new LaravelEventPublisher();
        });
        $this->app->bind(HDAddressGeneratorInterface::class, function ($app) {
            return new HDAddressGenerator();
        });
        $this->app->bind(AuthUserProvider::class, function ($app) {
            return new EloquentAuthUserProvider();
        });

        $this->app->bind(AddressGeneratorInterface::class, function ($app) {
            return new AddressGenerator();
        });
        $this->app->bind(ConfirmationRequirementResolver::class, function ($app) {
            return new EloquentConfirmationRequirementResolver();
        });

//        $this->app->bind(DepositRepository::class, function ($app) {
//
//            return new EloquentDepositRepository($app->make(DepositMapper::class));
//        });
//        $this->app->bind(DepositUniquenessChecker::class, function ($app) {
//            return new EloquentDepositUniquenessChecker();
//        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
