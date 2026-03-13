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
