<?php

namespace App\Application\Wallet\Handlers;

use App\Application\Wallet\Commands\ActivateWalletAddressCommand;
use App\Application\Wallet\Commands\LockWalletCommand;
use App\Domain\Shared\EventPublisher;
use App\Domain\Wallet\Entities\Wallet;
use App\Domain\Wallet\Repositories\WalletRepository;
use App\Domain\Wallet\ValueObjects\WalletId;

class ActivateWalletAddressHandler
{
    public function __construct(
        private WalletRepository            $walletRepository,
        private EventPublisher              $events
    ) {}
    public function handle(ActivateWalletAddressCommand $command) : Wallet
    {
        $walletId = WalletId::fromInt($command->walletId);
        $wallet = $this->walletRepository->findById($walletId);
        $wallet->activate();
        $this->walletRepository->save($wallet);

        $this->events->publish($wallet->pullDomainEvents());

        return $wallet;
    }
}
