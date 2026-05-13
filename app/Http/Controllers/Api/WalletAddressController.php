<?php

namespace App\Http\Controllers\Api;

use App\Application\Wallet\Commands\IssueWalletAddressCommand;
use App\Application\Wallet\Handlers\IssueWalletAddressHandler;
use App\Http\Requests\CreateAddressRequest;
use App\Http\Resources\DomainWalletResource;
use Illuminate\Support\Facades\Auth;

class WalletAddressController
{
    public function store(CreateAddressRequest $request, IssueWalletAddressHandler  $issueWalletAddress)
    {
//        $data = $request->validated();
//        $wallet = $createWallet->handle(new IssueWalletAddressCommand(Auth::id(),$data['currency_network_id']));

        $validated = $request->validated();

        /**IssueWalletAddressCommand
         *
         * public int $userId,
         * public int $networkId,
         * public string $networkCode,
         * public int $currencyNetworkId
         */

        $wallet = $issueWalletAddress->handle(new IssueWalletAddressCommand(
            //userId: Auth::check() ? Auth::id() : 0,
            userId: Auth::id(),
            networkId: $validated['network_id'],
            networkCode: $validated['network_code'],
            currencyNetworkId: (int)$validated['currency_network_id'],
        ));

        //return response()->json(new DomainWalletResource($wallet),201);
        return response()->json(new DomainWalletResource($wallet),201);
    }
}
