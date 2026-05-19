<?php

namespace App\Http\Controllers\Api;

use App\Application\Wallet\Commands\CreateWalletCommand;
use App\Application\Wallet\Handlers\CreateWalletHandler;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateWalletRequest;
use App\Http\Resources\DomainWalletResource;
use App\Http\Resources\WalletResource;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentCurrencyNetwork;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentWallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class WalletController extends Controller
{
    public function index(Request $request)
    {
        $wallets = EloquentWallet::query()
            ->where('user_id', Auth::id())
            ->leftJoin('currency_networks', 'currency_networks.id', '=', 'wallets.currency_network_id')
            ->leftJoin('currencies', 'currencies.id', '=', 'currency_networks.currency_id')
            ->leftJoin('networks', 'networks.id', '=', 'currency_networks.network_id')
            ->leftJoin('accounts', function ($join) {
                $join->on('accounts.owner_id', '=', 'wallets.user_id')
                    ->where('accounts.owner_type', 'user');
            })
            ->leftJoin('wallet_addresses', 'wallet_addresses.id', '=', 'wallets.active_address_id')
            ->select(
                'wallets.id',
                'wallets.status',
                'currencies.code as currency_code',
                'networks.code as network_code',
                'accounts.balance',
                'accounts.reserved_balance',
                'wallet_addresses.address'
            )->get();

        return response()->json(WalletResource::collection($wallets));
    }

    public function show(Request $request, string $wallet)
    {
        $wallet = EloquentWallet::query()
            ->where('user_id', Auth::id())
            ->leftJoin('currency_networks', 'currency_networks.id', '=', 'wallets.currency_network_id')
            ->leftJoin('currencies', 'currencies.id', '=', 'currency_networks.currency_id')
            ->leftJoin('networks', 'networks.id', '=', 'currency_networks.network_id')
            ->leftJoin('accounts', function ($join) {
                $join->on('accounts.owner_id', '=', 'wallets.user_id')
                    ->where('accounts.owner_type', 'user');
            })
            ->leftJoin('wallet_addresses', 'wallet_addresses.id', '=', 'wallets.active_address_id')
            ->select(
                'wallets.*',
                'currencies.code as currency_code',
                'networks.id as network_id',
                'networks.code as network_code',
                DB::raw('COALESCE(accounts.balance, 0) as available_balance'),
                DB::raw('COALESCE(accounts.reserved_balance, 0) as locked_balance'),
                'wallet_addresses.address as active_address'
            )
            ->where('wallets.id', $wallet)
            ->with('addresses')->first();

        return $wallet;
    }

    public function create()
    {
        $exist_pair = EloquentWallet::where('user_id', Auth::id())
            ->pluck('currency_network_id');

        $query = EloquentCurrencyNetwork::leftJoin('networks', 'networks.id', '=', 'currency_networks.network_id')
            ->leftJoin('currencies', 'currencies.id', '=', 'currency_networks.currency_id')
            ->select('currency_networks.id','currencies.code as currency_code', 'networks.code as network_code')
            ->where('currency_networks.is_active', true);

        if ($exist_pair->isNotEmpty()) {
            $query->whereNotIn('currency_networks.id', $exist_pair);
        }

        return response()->json($query->get());
    }
    public function store(CreateWalletRequest $request, CreateWalletHandler  $createWallet)
    {
        $data = $request->validated();
        $wallet = $createWallet->handle(new CreateWalletCommand(Auth::id(),$data['currency_network_id']));

        return response()->json(new DomainWalletResource($wallet),201);
    }

}
