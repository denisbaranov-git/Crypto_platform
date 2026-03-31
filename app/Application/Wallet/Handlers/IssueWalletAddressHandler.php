<?php

namespace App\Application\Wallet\Handlers;

use App\Application\Wallet\Commands\IssueWalletAddressCommand;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Shared\EventPublisher;
use App\Domain\Wallet\Repositories\HdWalletRepository;
use App\Domain\Wallet\Repositories\WalletRepository;
use App\Domain\Wallet\Services\HdAddressGeneratorInterface;
use App\Domain\Wallet\ValueObjects\CurrencyNetworkId;
use App\Domain\Wallet\ValueObjects\DerivationPath;
use App\Domain\Wallet\ValueObjects\NetworkCode;
use App\Domain\Wallet\ValueObjects\NetworkId;
use App\Domain\Wallet\ValueObjects\WalletAddressValue;
use App\Domain\Wallet\ValueObjects\WalletStatus;
use App\Domain\Wallet\ValueObjects\XPub;
use App\Models\WalletAddress;
use Illuminate\Support\Facades\DB;

final class IssueWalletAddressHandler
{
    public function __construct(
        private WalletRepository            $wallets,
        private HdWalletRepository          $hdWallets,
        private HdAddressGeneratorInterface $generator,
        private EventPublisher              $events
    ) {}

    public function handle(IssueWalletAddressCommand $command): WalletAddress
    {
        return DB::transaction(function () use ($command) {

            $userId = UserId::fromInt($command->userId);
            $currencyNetworkId = CurrencyNetworkId::fromInt($command->currencyNetworkId);

            $wallet = $this->wallets->getByUserAndCurrencyNetwork($userId, $currencyNetworkId);

            if ($wallet->status() !== WalletStatus::ACTIVE) {
                throw new \DomainException('Wallet is not active');
            }

            $networkId = NetworkId::fromInt($command->networkId);
            $networkCode = NetworkCode::fromString($command->networkCode);

            $hdWallet = $this->hdWallets->lockForNetwork($networkId);
            $index =$hdWallet->nextIndex();

            $xpub = XPub::fromString(
                config("wallet.{$networkCode->value()}_xpub")
            );

            $generated = $this->generator->generate($networkCode, $xpub, $index);

            $walletAddress = $wallet->issueAddress(
                WalletAddressValue::fromString($generated->address()),
                $index,
                DerivationPath::fromString($generated->path())
            );

            $this->wallets->save($wallet);

            $hdWallet->incrementNextIndex();
            $this->hdWallets->save($hdWallet);

            DB::afterCommit(fn() =>
            $this->events->publishAfterCommit($wallet->pullDomainEvents())
            );

            return $walletAddress;
        });
    }
}
