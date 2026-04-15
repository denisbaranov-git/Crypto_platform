<?php

declare(strict_types=1);

namespace App\Infrastructure\Ledger\Services;

use App\Domain\Ledger\Contracts\SystemAccountResolverInterface;
use App\Domain\Ledger\Entities\Account;
use App\Infrastructure\Persistence\Eloquent\Mappers\AccountMapper;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentAccount;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentSystemAccount;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use DomainException;

final class EloquentSystemAccountResolver implements SystemAccountResolverInterface
{
    public function resolveByCode(string $code, int $currencyNetworkId): Account
    {
        return DB::transaction(function () use ($code, $currencyNetworkId): Account {
            $systemAccount = EloquentSystemAccount::query()
                ->where('code', $code)
                ->where('is_active', true)
                ->first();

            if (! $systemAccount) {
                throw new DomainException("System account [$code] not found or inactive.");
            }

            $account = EloquentAccount::query()
                ->where('owner_type', 'system')
                ->where('owner_id', $systemAccount->id)
                ->where('currency_network_id', $currencyNetworkId)
                ->lockForUpdate()
                ->first();

            if (! $account) {
                try {
                    $account = EloquentAccount::create([
                        'owner_type' => 'system',
                        'owner_id' => $systemAccount->id,
                        'currency_network_id' => $currencyNetworkId,
                        'balance' => '0',
                        'reserved_balance' => '0',
                        'status' => 'active',
                        'version' => 0,
                        'metadata' => [
                            'system_account_code' => $code,
                        ],
                    ]);
                } catch (QueryException $e) {
                    $account = EloquentAccount::query()
                        ->where('owner_type', 'system')
                        ->where('owner_id', $systemAccount->id)
                        ->where('currency_network_id', $currencyNetworkId)
                        ->lockForUpdate()
                        ->first();

                    if (! $account) {
                        throw $e;
                    }
                }
            }

            return AccountMapper::toDomain($account);
        });
    }
}
