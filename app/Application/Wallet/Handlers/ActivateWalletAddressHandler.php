<?php

namespace App\Application\Wallet\Handlers;

use App\Application\Wallet\Commands\ActivateWalletAddressCommand;
use App\Application\Wallet\Commands\LockWalletCommand;
use App\Domain\Shared\EventPublisher;
use App\Domain\Wallet\Entities\Wallet;
use App\Domain\Wallet\Repositories\WalletAddressRepository;
use App\Domain\Wallet\Repositories\WalletRepository;
use App\Domain\Wallet\ValueObjects\WalletAddressId;
use App\Domain\Wallet\ValueObjects\WalletId;
use Illuminate\Support\Facades\DB;

class ActivateWalletAddressHandler
{
    public function __construct(
        private WalletRepository            $walletRepository,
        private EventPublisher              $events
    ) {}
    public function handle(ActivateWalletAddressCommand $command) : Wallet
    {
        return DB::transaction(function () use ($command) {
            $walletId = WalletId::fromInt($command->walletId);
            $activeAddressId = WalletAddressId::fromInt($command->newActiveAddressId);
            $wallet = $this->walletRepository->findById($walletId);
            $wallet->activateAddress($activeAddressId);

            $this->walletRepository->save($wallet);
            $this->events->publishAfterCommit($wallet->pullDomainEvents());

            return $wallet;
        });
    }
}
