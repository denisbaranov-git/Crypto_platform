<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Services;

use App\Domain\Wallet\Services\GeneratedAddress;

interface HDAddressGeneratorInterface
{
    public function generate(string $network, int $index): GeneratedAddress;

    /**
     * @return GeneratedAddress[]
     */
    public function generateMultiple(string $network, int $startIndex, int $count): array;
}
