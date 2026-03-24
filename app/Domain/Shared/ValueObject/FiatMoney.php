<?php

namespace App\Domain\Shared\ValueObject;

use InvalidArgumentException;

class FiatMoney
{
    public function __construct(
        public readonly string $currency,
        public readonly string $amount,
//        public readonly array $availableCurrencies, //['USD','EUR']
    ) {
//        if (!in_array($currency, $availableCurrencies)) {
//            throw new InvalidArgumentException("Unsupported currency");
//        }

        if (bccomp($amount, '0', 2) <= 0) {
            throw new InvalidArgumentException("Amount must be > 0");
        }
    }
}
