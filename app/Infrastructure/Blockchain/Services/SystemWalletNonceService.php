<?php

declare(strict_types=1);

namespace App\Infrastructure\Blockchain\Services;

use App\Infrastructure\Blockchain\Support\JsonRpcClient;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentNetwork;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentSystemWallet;
use DomainException;
use Illuminate\Support\Facades\DB;

final class SystemWalletNonceService
{
    public function reserveNextNonce(int $networkId, int $systemWalletId): int
    {
        $network = EloquentNetwork::query()->findOrFail($networkId);

        if ($network->rpc_driver !== 'evm') {
            throw new DomainException('Nonce reservation is only for EVM networks.');
        }

        $rpc = new JsonRpcClient((string) $network->rpc_url, null);

        return DB::transaction(function () use ($systemWalletId, $rpc): int {
            $wallet = EloquentSystemWallet::query()
                ->whereKey($systemWalletId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($wallet->type !== 'hot' || $wallet->status !== 'active') {
                throw new DomainException('System wallet is not active hot wallet.');
            }

            if ((int) $wallet->next_nonce <= 0) {
                $pendingHex = (string) $rpc->call('eth_getTransactionCount', [
                    $wallet->address,
                    'pending',
                ]);

                $wallet->next_nonce = hexdec(preg_replace('/^0x/', '', $pendingHex) ?: '0');
                //$wallet->nonce_synced_at = now();
            }

            $nonce = (int) $wallet->next_nonce;
            $wallet->next_nonce = $nonce + 1;
            $wallet->nonce_synced_at = now();
            $wallet->save();

            return $nonce;
        });
    }
}
