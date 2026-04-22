<?php

declare(strict_types=1);

namespace App\Infrastructure\Blockchain\Services;

use App\Infrastructure\Persistence\Eloquent\Models\EloquentSystemWallet;
use Illuminate\Support\Facades\Crypt;
use DomainException;

final class SystemWalletSecretResolver
{
    public function privateKeyForSystemWalletId(int $systemWalletId): string
    {
        $wallet = EloquentSystemWallet::query()->find($systemWalletId);

        if (! $wallet) {
            throw new DomainException("System wallet [$systemWalletId] not found.");
        }

        if ($wallet->status !== 'active') {
            throw new DomainException("System wallet [$systemWalletId] is not active.");
        }

        try {
            return Crypt::decryptString((string) $wallet->encrypted_private_key);
        } catch (\Throwable $e) {
            throw new DomainException("Cannot decrypt private key for system wallet [$systemWalletId].", 0, $e);
        }
    }
}
