<?php

namespace App\Application\Wallet\Handlers;

use App\Application\Wallet\Commands\LockWalletCommand;
use App\Domain\Shared\EventPublisher;
use App\Domain\Wallet\Entities\Wallet;
use App\Domain\Wallet\ValueObjects\WalletId;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentWalletRepository;
use Illuminate\Support\Facades\DB;

class LockWalletHandler
{
    public function __construct(
        private EloquentWalletRepository $walletRepository,
        private EventPublisher           $events
    ){}
    public function handle(LockWalletCommand $command) : Wallet
    {
            $walletId = WalletId::fromInt($command->walletId);
            $wallet = $this->walletRepository->findById($walletId);
            $wallet->lock();
            $this->walletRepository->save($wallet);

            $this->events->publish($wallet->pullDomainEvents());

            return $wallet;
    }
}
