<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Shared\Contracts\CurrencyNetworkProviderInterface;
use App\Domain\Wallet\Repositories\HdWalletRepository;
use App\Domain\Wallet\Repositories\WalletRepository;
use App\Domain\Wallet\Services\HDAddressGeneratorInterface;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentHdWalletRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentWalletRepository;
use App\Infrastructure\Persistence\Eloquent\Shared\EloquentCurrencyNetworkProvider;
use App\Services\Wallet\AddressGenerator;
use App\Services\Wallet\AddressGeneratorInterface;
use App\Services\Wallet\HDAddressGenerator;
use Illuminate\Support\ServiceProvider;
use App\Services\Wallet\Crypto\Bip39MnemonicService;
use App\Services\Wallet\Crypto\Bip32KeyService;
use App\Services\Wallet\Crypto\Contracts\Bip32KeyServiceInterface;

final class WalletServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(WalletRepository::class, EloquentWalletRepository::class);
        $this->app->bind(HdWalletRepository::class, EloquentHdWalletRepository::class);
        $this->app->bind(HDAddressGeneratorInterface::class,HDAddressGenerator::class);
        $this->app->bind(CurrencyNetworkProviderInterface::class,EloquentCurrencyNetworkProvider::class);

//        $this->app->singleton(BIP32::class, function () {
//            return new BIP32();
//        });
//
//        $this->app->singleton(Bip39MnemonicService::class, function () {
//            return new Bip39MnemonicService();
//        });
        $this->app->singleton(Bip39MnemonicService::class);
        $this->app->singleton(Bip32KeyServiceInterface::class, Bip32KeyService::class);

        $this->app->bind(AddressGeneratorInterface::class, function ($app) {
            return new AddressGenerator();
        });
//        $this->app->bind(HDAddressGeneratorInterface::class, function ($app) {
//            return new HDAddressGenerator();
//        });

    }
}
