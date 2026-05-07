<?php

namespace App\Http\Controllers\Api;

use App\Application\Wallet\Commands\CreateWalletCommand;
use App\Application\Wallet\Commands\IssueWalletAddressCommand;
use App\Application\Wallet\Handlers\CreateWalletHandler;
use App\Application\Wallet\Handlers\IssueWalletAddressHandler;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateAddressRequest;
use App\Http\Requests\CreateWalletRequest;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentCurrencyNetwork;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentWallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class WalletController extends Controller
{
    public function index(Request $request): array
    {
        return [
            'data' => [
                [
                    'id' => 1,
                    'currency_code' => 'USDT',
                    'network_code' => 'tron',
                    'available_balance' => '300.000000',
                    'locked_balance' => '0.000000',
                    'active_address' => 'TXYZ...',
                    'status' => 'active',
                ],
                [
                    'id' => 2,
                    'currency_code' => 'BTC',
                    'network_code' => 'bitcoin',
                    'available_balance' => '0.04200000',
                    'locked_balance' => '0.00000000',
                    'active_address' => 'bc1q...',
                    'status' => 'active',
                ],
            ],
        ];
    }

    public function show(Request $request, string $wallet): array
    {
        return [
            'id' => $wallet,
            'currency_network_id' => 1,
            'network_id' => 1,
            'currency_code' => 'USDT',
            'network_code' => 'tron',
            'available_balance' => '300.000000',
            'locked_balance' => '0.000000',
            'active_address' => 'TXYZ...',
            'addresses' => [
                ['address' => 'TXYZ...', 'is_active' => true],
            ],
            'deposits' => [],
        ];
    }

    public function create()
    {
        $exist_pair = EloquentWallet::where('user_id', Auth::id())
            ->pluck('currency_network_id');

        $query = EloquentCurrencyNetwork::leftJoin('networks', 'networks.id', '=', 'currency_networks.network_id')
            ->leftJoin('currencies', 'currencies.id', '=', 'currency_networks.currency_id')
            ->select('currency_networks.id', 'currencies.code as currency_code', 'networks.code as network_code');

        if ($exist_pair->isNotEmpty()) {
            $query->whereNotIn('currency_networks.id', $exist_pair);
        }

        return response()->json($query->get());
    }
    public function store(CreateWalletRequest $request, CreateWalletHandler  $createWallet)
    {

        $data = $request->validated();
        $createWallet->handle(new CreateWalletCommand(Auth::id(),$data['network_id'], $data['currency_code'],$data['currency_network_id']));

        return response()->json('fuck!!!!!!!!!',201);
    }

    public function createAddress(CreateAddressRequest $request, IssueWalletAddressHandler $issueWalletAddress, string $wallet): array
    {
//        public int $userId,
//        public int $networkId,
//        public string $networkCode,
//        public int $currencyNetworkId

//        $table->id();
//        $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
//        $table->foreignId('network_id')->constrained();
//        $table->foreignId('currency_network_id')->constrained('currency_networks');
//        $table->string('address', 255);
//        $table->unsignedBigInteger('derivation_index');
//        $table->string('derivation_path', 255);
//        $table->string('status')->default('active');
//        $table->boolean('is_active')->default(true);
//        $table->timestamps();
        $validated = $request->validated();

        $address = $issueWalletAddress->handle(new IssueWalletAddressCommand(
            userId: Auth::check() ? Auth::id() : 0,
            networkId: $validated['network_id'],
            networkCode: $validated['network_code'],
            currencyNetworkId: (int)$validated['network_code'],
        ));

        return [
            'id' => $wallet,
            'currency_network_id' => 1,
            'network_id' => 1,
            'currency_code' => 'USDT',
            'network_code' => 'tron',
            'available_balance' => '300.000000',
            'locked_balance' => '0.000000',
            'active_address' => 'TXYZ...',
            'addresses' => [
                ['address' => 'TXYZ...', 'is_active' => true],
            ],
            'deposits' => [],
        ];

    }
}
