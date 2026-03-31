<?php

namespace App\Domain\Deposit\Events;

final readonly class DepositCredited
{
    public function __construct(
        public string $depositKey,
        public int $userId,
        public int $currencyId,
        public string $amount,
        public string $ledgerOperationId,
    ) {}
}

//final class DepositCredited
//{
//    public function __construct(
//        public int $depositId,
//        public int $userId,
//        public int $currencyId,
//        public string $amount,
//        public string $txid
//    ) {}
//}
