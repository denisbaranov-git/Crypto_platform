<?php

namespace App\Application\Wallet\Handlers;

use App\Application\Wallet\Commands\ArchiveWalletCommand;
use App\Domain\Shared\EventPublisher;
use App\Domain\Wallet\Entities\Wallet;
use App\Domain\Wallet\ValueObjects\WalletId;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentWalletRepository;

class ArchiveWalletHandler
{
    public function __construct(
        private EloquentWalletRepository $walletRepository,
        private EventPublisher           $events
    ){}
    public function handle(ArchiveWalletCommand $command) : Wallet
    {
        $walletId = WalletId::fromInt($command->walletId);
        $wallet = $this->walletRepository->findById($walletId);
        $wallet->archive();
        $this->walletRepository->save($wallet);

        $this->events->publish($wallet->pullDomainEvents());

        return $wallet;
    }
}
