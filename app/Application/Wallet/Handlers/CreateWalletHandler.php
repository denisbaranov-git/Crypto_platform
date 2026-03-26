<?php

namespace App\Application\Wallet\Handlers;

use App\Application\Wallet\Commands\CreateWalletCommand;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Wallet\Entities\Wallet;
use App\Domain\Wallet\Entities\WalletAddress;
use App\Domain\Wallet\Repositories\HdWalletRepository;
use App\Domain\Wallet\Repositories\WalletRepository;
use App\Domain\Wallet\ValueObjects\CurrencyNetworkId;
use App\Domain\Wallet\ValueObjects\DerivationIndex;
use App\Domain\Wallet\ValueObjects\DerivationPath;
use App\Domain\Wallet\ValueObjects\NetworkId;
use App\Domain\Wallet\ValueObjects\WalletAddressValue;
use Illuminate\Support\Facades\DB;

final readonly class CreateWalletHandler
{
    public function __construct(
        private WalletRepository $wallets,
        private HdWalletRepository $hdWallets,
        private BlockchainAddressGenerator $generator,
        private WalletMapper $mapper,
        private WalletEventPublisher $publisher
    ) {}

    public function handle(CreateWalletCommand $command): Wallet
    {
        return DB::transaction(function () use ($command) {
            $userId = UserId::fromInt($command->userId);
            $currencyNetworkId = CurrencyNetworkId::fromInt($command->currencyNetworkId);

            if ($this->wallets->existsByUserAndCurrencyNetwork($userId, $currencyNetworkId)) {
                throw new \DomainException('Wallet already exists');
            }

            $wallet = Wallet::create($userId, $currencyNetworkId);

            $hdWallet = $this->hdWallets->lockForNetwork(
                NetworkId::fromInt($currencyNetworkId->value())
            );

            $index = $hdWallet->nextIndex();

            $generated = $this->generator->generate(
                networkCode: $hdWallet->networkCode(),
                xpub: $hdWallet->xpub(),
                index: $index
            );

            $address = WalletAddress::create(
                address: WalletAddressValue::fromString($generated->address),
                derivationIndex: DerivationIndex::fromInt($index),
                derivationPath: DerivationPath::fromString($generated->path)
            );

            $wallet->issueAddress($address);

            $this->wallets->save($wallet);
            $this->hdWallets->save($hdWallet->advanceNextIndex());

            $this->publisher->publishAfterCommit($wallet->pullDomainEvents());

            return $wallet;
        });
    }
}
