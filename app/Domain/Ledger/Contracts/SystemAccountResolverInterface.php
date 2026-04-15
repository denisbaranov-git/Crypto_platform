<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Contracts;

use App\Domain\Ledger\Entities\Account;

interface SystemAccountResolverInterface
{
    public function resolveByCode(string $code, int $currencyNetworkId): Account;
}
