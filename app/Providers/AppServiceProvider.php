<?php

namespace App\Providers;

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
        $this->app->bind(AddressGeneratorInterface::class, function ($app) {
            return new AddressGenerator();
        });

        $this->app->bind(HDAddressGeneratorInterface::class, function ($app) {
            return new HDAddressGenerator();
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
