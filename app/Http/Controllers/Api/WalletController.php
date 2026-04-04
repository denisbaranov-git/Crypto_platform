<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

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
