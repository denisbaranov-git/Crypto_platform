<?php

namespace App\Http\Controllers\Api;

use App\Application\Wallet\Commands\ActivateWalletAddressCommand;
use App\Application\Wallet\Commands\IssueWalletAddressCommand;
use App\Application\Wallet\Handlers\ActivateWalletAddressHandler;
use App\Application\Wallet\Handlers\IssueWalletAddressHandler;
use App\Http\Requests\ActivateWalletAddressRequest;
use App\Http\Requests\CreateAddressRequest;
use App\Http\Resources\DomainAddressResource;
use App\Http\Resources\DomainWalletResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WalletAddressController
{
    public function store(CreateAddressRequest $request, IssueWalletAddressHandler  $issueWalletAddress)
    {
        $validated = $request->validated();

        //$wallet = $issueWalletAddress->handle(new IssueWalletAddressCommand( //denis need return WalletAddress
        $address = $issueWalletAddress->handle(new IssueWalletAddressCommand(
            //userId: Auth::check() ? Auth::id() : 0,
            userId: Auth::id(),
            networkId: $validated['network_id'],
            networkCode: $validated['network_code'],
            currencyNetworkId: (int)$validated['currency_network_id'],
        ));

        //return response()->json(new DomainWalletResource($wallet),201);//denis need return WalletAddress
         return response()->json(new DomainAddressResource($address),201);
    }
    public function activate(ActivateWalletAddressRequest $request, ActivateWalletAddressHandler $activateWalletAddressHandler, $wallet, $address)
    {
        $validated = $request->validated();
        $wallet = $activateWalletAddressHandler->handle(new ActivateWalletAddressCommand(
            walletId: $validated['wallet'],
            newActiveAddressId: $validated['address'],
        ));

        return response()->json(new DomainWalletResource($wallet),201);
    }
}
