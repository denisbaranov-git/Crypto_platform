<?php

declare(strict_types=1);

namespace App\Services\Wallet;

use App\Infrastructure\Persistence\Eloquent\Models\EloquentSystemWallet;
use RuntimeException;

class SystemWalletService
{
    /**
     * Создает системный кошелек.
     *
     * @param int $networkId ID сети
     * @param string $address Адрес кошелька
     * @param string $encryptedPrivateKey Зашифрованный приватный ключ
     * @param string $type Тип кошелька (hot/cold/sweep/fee)
     * @param array $metadata Дополнительные метаданные (опционально)
     * @return EloquentSystemWallet
     * @throws RuntimeException Если кошелек с таким адресом уже существует
     */
    public function createWallet(
        int $networkId,
        string $address,
        string $encryptedPrivateKey,
        string $type = 'hot',
        array $metadata = []
    ): EloquentSystemWallet {
        // Проверяем уникальность [network_id, address]
        $exists = EloquentSystemWallet::query()
            ->where('network_id', $networkId)
            ->where('address', $address)
            ->exists();

        if ($exists) {
            throw new RuntimeException(
                "System wallet already exists for network {$networkId} with address {$address}"
            );
        }

        return EloquentSystemWallet::query()->create([
            'network_id' => $networkId,
            'address' => $address,
            'type' => $type,
            'encrypted_private_key' => $encryptedPrivateKey,
            'next_nonce' => 0,
            'current_nonce' => 0,
            'nonce_synced_at' => null,
            'status' => 'active',
        ]);
    }

    /**
     * Получить активные кошельки для сети по типу.
     *
     * @param int $networkId
     * @param string $type
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getActiveWallets(int $networkId, string $type = 'hot')
    {
        return EloquentSystemWallet::query()
            ->where('network_id', $networkId)
            ->where('type', $type)
            ->where('status', 'active')
            ->get();
    }

    /**
     * Обновить nonce для кошелька.
     *
     * @param int $walletId
     * @param int $nextNonce
     * @param int $currentNonce
     * @return bool
     */
    public function updateNonce(int $walletId, int $nextNonce, int $currentNonce): bool
    {
        return EloquentSystemWallet::query()
                ->where('id', $walletId)
                ->update([
                    'next_nonce' => $nextNonce,
                    'current_nonce' => $currentNonce,
                    'nonce_synced_at' => now(),
                ]) > 0;
    }
}
