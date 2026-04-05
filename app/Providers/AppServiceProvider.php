<?php

namespace App\Providers;

use App\Domain\Identity\Repositories\UserRepository;
use App\Domain\Shared\EventPublisher;
use App\Infrastructure\Auth\Contracts\AuthUserProvider;
use App\Infrastructure\Auth\EloquentAuthUserProvider;
use App\Infrastructure\Events\LaravelEventPublisher;
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

    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
