<?php

namespace App\Domain\Wallet\Entities;

use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Shared\RecordsDomainEvents;
use App\Domain\Wallet\Events\WalletActivated;
use App\Domain\Wallet\Events\WalletAddressActivated;
use App\Domain\Wallet\Events\WalletAddressIssued;
use App\Domain\Wallet\Events\WalletArchived;
use App\Domain\Wallet\Events\WalletLocked;
use App\Domain\Wallet\ValueObjects\CurrencyNetworkId;
use App\Domain\Wallet\ValueObjects\DerivationPath;
use App\Domain\Wallet\ValueObjects\WalletAddressId;
use App\Domain\Wallet\ValueObjects\WalletAddressValue;
use App\Domain\Wallet\ValueObjects\WalletId;
use App\Domain\Wallet\ValueObjects\WalletStatus;

final class Wallet
{
    use RecordsDomainEvents;
    private ?WalletId $id;
    private UserId $userId;
    private CurrencyNetworkId $currencyNetworkId;
    private WalletStatus $status;
    private array $addresses = [];
    private ?WalletAddressId $activeAddressId = null;
    private array $events = [];

    private function __construct(
        UserId $userId,
        CurrencyNetworkId $currencyNetworkId
    ) {
        $this->id = null;
        $this->userId = $userId;
        $this->currencyNetworkId = $currencyNetworkId;
        $this->status = WalletStatus::ACTIVE;
    }

    public static function create(
        UserId $userId,
        CurrencyNetworkId $currencyNetworkId
    ): self {
        return new self($userId, $currencyNetworkId);
    }

    public static function hydrate(
        WalletId $id,
        UserId $userId,
        CurrencyNetworkId $currencyNetworkId,
        WalletStatus $status,
        ?WalletAddressId $activeAddressId,
        array $addresses
    ): self {
        $wallet = new self($userId, $currencyNetworkId);
        $wallet->id = $id;
        $wallet->status = $status;
        $wallet->activeAddressId = $activeAddressId;
        $wallet->addresses = $addresses;

        return $wallet;
    }

    public function issueAddress(WalletAddressValue $address, int $index, DerivationPath $path): WalletAddress
    {
        if ($this->status !== WalletStatus::ACTIVE) {
            throw new \DomainException('Wallet is not active');
        }

        foreach ($this->addresses as $existing) {
            if ($existing->derivationIndex() === $index) {
                throw new \DomainException('Derivation index already used in wallet');
            }
            if ($existing->address()->equals($address)) {
                throw new \DomainException('Address already exists');
            }
        }

        $walletAddress = WalletAddress::create(
            address: $address,
            derivationIndex: $index,
            derivationPath:$path,
        );

        $this->addresses[] = $walletAddress;

        if ($this->activeAddressId === null) {
            $this->activeAddressId = $walletAddress->id();
        }

        $this->recordDomainEvent(new WalletAddressIssued(
            $this->id->value(),
            $walletAddress->address()->value(),
            $walletAddress->derivationIndex(),
        ));

        return $walletAddress;
    }

    public function lock(): void
    {
        $this->status = WalletStatus::LOCKED;
        $this->recordDomainEvent( new WalletLocked($this->id->value()));
    }

    public function archive(): void
    {
        $this->status = WalletStatus::ARCHIVED;
        $this->recordDomainEvent( new WalletArchived($this->id->value()));
    }

    public function activate(): void
    {
        $this->status = WalletStatus::ACTIVE;
        $this->recordDomainEvent( new WalletActivated($this->id->value()));
    }

    public function activateAddress(WalletAddressId $addressId): void
    {
        if (!$this->hasAddress($addressId)) {
            throw new \DomainException('Address does not belong to this wallet');
        }


        $this->activeAddressId = $addressId;

        $this->recordDomainEvent( new WalletAddressActivated($this->id->value(), $addressId->value()));
    }

    private function hasAddress(WalletAddressId $addressId): bool
    {
        foreach ($this->addresses as $address) {
            if ($address->id()?->equals($addressId)) {
                return true;
            }
        }

        return false;
    }

    public function id(): ?WalletId
    {
        return $this->id;
    }

    public function userId(): UserId
    {
        return $this->userId;
    }

    public function currencyNetworkId(): CurrencyNetworkId
    {
        return $this->currencyNetworkId;
    }

    public function status(): WalletStatus
    {
        return $this->status;
    }

    public function activeAddressId(): ?WalletAddressId
    {
        return $this->activeAddressId;
    }

    /** @return WalletAddress[] */
    public function addresses(): array
    {
        return $this->addresses;
    }

    public function assignId(WalletId $id): void
    {
        if ($this->id !== null) {
            throw new \LogicException('Wallet id already assigned');
        }

        $this->id = $id;
    }
}
