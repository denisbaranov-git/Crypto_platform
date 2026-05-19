<?php

namespace App\Application\Wallet\Handlers;

use App\Application\Wallet\Commands\IssueWalletAddressCommand;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Shared\EventPublisher;
use App\Domain\Wallet\Entities\Wallet;
use App\Domain\Wallet\Entities\WalletAddress;
use App\Domain\Wallet\Repositories\HdWalletRepository;
use App\Domain\Wallet\Repositories\WalletRepository;
use App\Domain\Wallet\Services\HDAddressGeneratorInterface;
use App\Domain\Wallet\ValueObjects\CurrencyNetworkId;
use App\Domain\Wallet\ValueObjects\DerivationPath;
use App\Domain\Wallet\ValueObjects\NetworkCode;
use App\Domain\Wallet\ValueObjects\NetworkId;
use App\Domain\Wallet\ValueObjects\WalletAddressValue;
use App\Domain\Wallet\ValueObjects\WalletStatus;
use App\Domain\Wallet\ValueObjects\XPub;
use Illuminate\Support\Facades\DB;

final class IssueWalletAddressHandler
{
    public function __construct(
        private WalletRepository            $walletsRepository,
        private HdWalletRepository          $hdWallets,
        private HDAddressGeneratorInterface $generator,
        private EventPublisher              $events
    ) {}

    public function handle(IssueWalletAddressCommand $command): WalletAddress//Wallet//
    {
        /**IssueWalletAddressCommand
         *
         * public int $userId,
         * public int $networkId,
         * public string $networkCode,
         * public int $currencyNetworkId
         */
        return DB::transaction(function () use ($command) {

            $userId = UserId::fromInt($command->userId);
            $currencyNetworkId = CurrencyNetworkId::fromInt($command->currencyNetworkId);

            $wallet = $this->walletsRepository->getByUserAndCurrencyNetwork($userId, $currencyNetworkId);

            if ($wallet->status() !== WalletStatus::ACTIVE) {
                throw new \DomainException('Wallet is not active');
            }

            $networkId = NetworkId::fromInt($command->networkId);
            $networkCode = NetworkCode::fromString($command->networkCode);

            $hdWallet = $this->hdWallets->lockForNetwork($networkId);
            $index =$hdWallet->nextIndex();

            $generated = $this->generator->generate($networkCode->value(), $index);

            $walletAddress = $wallet->issueAddress( //->address create, add to Wallet->addresses
                WalletAddressValue::fromString($generated->address()),
                $generated->chain(),
                $index,
                DerivationPath::fromString($generated->path())
            );

            $this->walletsRepository->save($wallet);

            //$walletAddress->assignId(//////id);

            $hdWallet->incrementNextIndex();
            $this->hdWallets->save($hdWallet);

//            DB::afterCommit(fn() =>
//                $this->events->publishAfterCommit($wallet->pullDomainEvents())
//            );

            $this->events->publishAfterCommit($wallet->pullDomainEvents());

            //return $wallet;//denis //не нужно!!!
            return $walletAddress;
        });
    }
}
