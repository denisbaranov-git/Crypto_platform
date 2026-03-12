<?php

namespace App\Providers;

use App\Services\Wallet\AddressGenerator;
use App\Services\Wallet\AddressGeneratorInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AddressGeneratorInterface::class, function ($app) {
            return new AddressGenerator();
        });    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
