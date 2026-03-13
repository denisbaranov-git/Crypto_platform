<?php

namespace App\Services\Wallet;

use App\Models\SystemWallet;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Carbon;

class SystemWalletService
{
    public function __construct(private AddressGeneratorInterface $addressGenerator){ }
    public function createWallet(int $network_id, string $network, string $type ): void ////denis нужно передавать только ( $network_id, $walletType )
    {
        $walletData = $this->addressGenerator->generate($network);

        SystemWallet::create([
            'network' => $network_id,
            'type' => $type,
            'address' => $walletData['address'],
            'encrypted_private_key' => Crypt::encryptString($walletData['private_key']),
            'status' => 'active',
            'created_at' => Carbon::now(),
        ]);
    }
}
