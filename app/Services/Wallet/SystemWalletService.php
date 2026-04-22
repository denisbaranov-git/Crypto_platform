<?php

namespace App\Services\Wallet;

use App\Infrastructure\Persistence\Eloquent\Models\EloquentSystemWallet;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;

class SystemWalletService
{
    public function __construct(private AddressGeneratorInterface $addressGenerator){ }
    public function createWallet(int $network_id, string $network, string $type ): void ////denis нужно передавать только ( $network_id, $walletType )
    {
        $walletData = $this->addressGenerator->generate($network);

        EloquentSystemWallet::create([
            'network' => $network_id,
            'type' => $type,
            'address' => $walletData['address'],
            'encrypted_private_key' => Crypt::encryptString($walletData['private_key']),
            'status' => 'active',
            'created_at' => Carbon::now(),
        ]);
    }
}
