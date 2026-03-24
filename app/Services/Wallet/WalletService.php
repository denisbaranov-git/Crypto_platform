<?php

namespace App\Services\Wallet;

class WalletService
{
    public function nextAddressIndex(): int
    {
        return WalletAddress::max('index') + 1;
    }
    public function createAddress($user_id, $network)
    {
    //BEGIN;
    //    SELECT next_index
    //    FROM hd_wallets
    //    WHERE network_id = ?
    //    FOR UPDATE;
    //
    //        index = next_index;
    //
    //        UPDATE hd_wallets
    //    SET next_index = next_index + 1;
    //COMMIT;
    //
    //👉 только после этого:
    //create wallet_address with index


        $index = WalletAddress::max('index') + 1;

        WalletAddress::create([
            'user_id' => $user_id,
            'index' => $index,
            'network' => $network,
        ]);
    }

    public function createDepositAddress( UserId $userId, Network $network ): Address
    {

        $wallet = $this->walletRepository->findByUser($userId);
        $generator = $this->generatorFactory->forNetwork($network);
        $address = $wallet->createDepositAddress($network,$generator);
        $this->walletRepository->save($wallet);

        return $address;
    }
//    public function createDepositAddress(User $user, Network $network)
//    {
//        $generator = $this->generatorFactory->forNetwork($network);
//        $address = $generator->generate($index);
//
//        return Address::create($user, $network, $address);
//    }

//    public function createDepositAddress(User $user, Network $network)
//    {
//        $wallet = $this->walletRepository->getUserWallet($user);
//        $address = $wallet->createDepositAddress($network);
//        $this->walletRepository->save($wallet);
//
//        return $address;
//    }
}
