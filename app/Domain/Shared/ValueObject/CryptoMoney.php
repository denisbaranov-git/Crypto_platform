<?php

namespace App\Domain\Shared\ValueObject;

use InvalidArgumentException;

class CryptoMoney
{
    public function __construct(
        public readonly string $currency,
        public readonly string $amount,
        //public readonly array $availableCurrencies, //['USDT','BTC']
    ) {
//        if (!in_array($currency, $availableCurrencies)) {
//            throw new InvalidArgumentException("Unsupported Crypto currency");
//        }

        if (bccomp($amount, '0', 18) <= 0) {
            throw new InvalidArgumentException("Crypto amount invalid must be > 0");
        }
    }
}
