<?php

namespace App\Application\Wallet\Handlers;

use App\Application\Shared\Contracts\CurrencyNetworkProviderInterface;
use App\Application\Wallet\Commands\CreateWalletCommand;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Shared\EventPublisher;
use App\Domain\Wallet\Entities\Wallet;
use App\Domain\Wallet\Repositories\HdWalletRepository;
use App\Domain\Wallet\Repositories\WalletRepository;
use App\Domain\Wallet\Services\HDAddressGeneratorInterface;
use App\Domain\Wallet\ValueObjects\CurrencyNetworkId;
use App\Domain\Wallet\ValueObjects\DerivationPath;
use App\Domain\Wallet\ValueObjects\NetworkId;
use App\Domain\Wallet\ValueObjects\WalletAddressValue;
use App\Domain\Wallet\ValueObjects\XPub;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

final class CreateWalletHandler
{
    public function __construct(
        private WalletRepository                 $walletsRepository,
        private HDAddressGeneratorInterface      $generator,
        private HdWalletRepository               $hdWallets,
        private CurrencyNetworkProviderInterface $currencyNetworkProvider,
        private EventPublisher                   $events
    ) {}

    public function handle(CreateWalletCommand $command): Wallet
    {
        return DB::transaction(function () use ($command) {

            $userId = UserId::fromInt($command->userId);
            $currencyNetworkId = CurrencyNetworkId::fromInt($command->currencyNetworkId);

            if ($this->walletsRepository->existsByUserAndCurrencyNetwork($userId, $currencyNetworkId)) {
                throw new \DomainException('Wallet exists');
            }

            $currencyNetwork = $this->currencyNetworkProvider->findById($currencyNetworkId->value());
            if (!$currencyNetwork) {
                throw new \DomainException('The selected currency/network pair does not exist');
            }
            $wallet = Wallet::create($userId, $currencyNetworkId);

            //$networkId = NetworkId::fromInt($command->networkId);
            //$networkCode = NetworkCode::fromString($command->networkCode);
            $networkId = NetworkId::fromInt($currencyNetwork->networkId);
            //$networkCode = NetworkCode::fromString($currencyNetwork->networkCode);

            $hdWallet = $this->hdWallets->lockForNetwork($networkId);
            $index =$hdWallet->nextIndex();

            $generated = $this->generator->generate($currencyNetwork->networkCode, $index);

            $wallet->issueAddress(
                WalletAddressValue::fromString($generated->address()),
                $generated->chain(),
                $index,
                DerivationPath::fromString($generated->path())
            );
            $this->walletsRepository->save($wallet);

            $hdWallet->incrementNextIndex();
            $this->hdWallets->save($hdWallet);

//            DB::afterCommit(fn() =>
//                $this->events->publishAfterCommit($wallet->pullDomainEvents())
//            );
            $this->events->publishAfterCommit($wallet->pullDomainEvents());

            return $wallet;
        });
    }
}
