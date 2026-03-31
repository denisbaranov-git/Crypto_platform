<?php

namespace App\Domain\Wallet\Services;
final class GeneratedAddress
{
    public function __construct(
        private readonly string $address,
        private readonly string $path,
    ) {}

    public function address(): string
    {
        return $this->address;
    }

    public function path(): string
    {
        return $this->path;
    }
}
