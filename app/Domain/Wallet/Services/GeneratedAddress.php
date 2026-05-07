<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Services;

final class GeneratedAddress
{
    public function __construct(
        private readonly string $address,
        private readonly string $path,
        private readonly int $chain,
        private readonly int $index,
    ) {
    }

    public function address(): string
    {
        return $this->address;
    }

    public function path(): string // evm  = "m/44'/60'/0'/chain/{$index->value()}";
    {
        return $this->path;
    }

    public function chain(): int
    {
        return $this->chain;
    }

    public function index(): int
    {
        return $this->index;
    }
}
