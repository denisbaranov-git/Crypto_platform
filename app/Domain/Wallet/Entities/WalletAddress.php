<?php

namespace App\Domain\Wallet\Entities;

use App\Domain\Wallet\ValueObjects\DerivationIndex;
use App\Domain\Wallet\ValueObjects\DerivationPath;
use App\Domain\Wallet\ValueObjects\WalletAddressId;
use App\Domain\Wallet\ValueObjects\WalletAddressValue;

class WalletAddress
{
    private ?WalletAddressId $id = null;
    private WalletAddressValue $address;
    private int $derivationIndex;
    private DerivationPath $derivationPath;
    private bool $isActive = true;
    private string $status = 'active';

    private function __construct(
        WalletAddressValue $address,
        int $derivationIndex,
        DerivationPath $derivationPath,
    ) {
        $this->address = $address;
        $this->derivationIndex = $derivationIndex;
        $this->derivationPath = $derivationPath;
    }

    public static function create(WalletAddressValue $address, int $derivationIndex, DerivationPath $derivationPath): self
    {
        return new self($address, $derivationIndex, $derivationPath);
    }

    public static function hydrate(
        WalletAddressId $id,
        WalletAddressValue $address,
        int $derivationIndex,
        DerivationPath $derivationPath,
        bool $isActive,
        string $status
    ): self {
        $entity = new self($address, $derivationIndex, $derivationPath);
        $entity->id = $id;
        $entity->isActive = $isActive;
        $entity->status = $status;

        return $entity;
    }

    public function id(): ?WalletAddressId
    {
        return $this->id;
    }

    public function address(): WalletAddressValue
    {
        return $this->address;
    }

    public function derivationIndex(): int
    {
        return $this->derivationIndex;
    }

    public function derivationPath(): DerivationPath
    {
        return $this->derivationPath;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }
    public function status(): string
    {
        return $this->status;
    }
}
