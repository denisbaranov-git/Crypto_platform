<?php

namespace App\Providers;

use App\Domain\Identity\Repositories\UserRepository;
use App\Domain\Shared\EventPublisher;
use App\Infrastructure\Auth\Contracts\AuthUserProvider;
use App\Infrastructure\Auth\EloquentAuthUserProvider;
use App\Infrastructure\Events\LaravelEventPublisher;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentUserRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(UserRepository::class, EloquentUserRepository::class);

        $this->app->bind(EventPublisher::class, function ($app) {
            return new LaravelEventPublisher();
        });

        $this->app->bind(AuthUserProvider::class, function ($app) {
            return new EloquentAuthUserProvider();
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
