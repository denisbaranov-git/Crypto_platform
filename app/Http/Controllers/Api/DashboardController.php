<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

final class DashboardController extends Controller
{
    public function __invoke(Request $request): array
    {
        //need to do -  pplication query/service. // denis
        return [
            'summary' => [
                'total_balance' => '1250.25000000',
                'available_balance' => '1200.25000000',
                'locked_balance' => '50.00000000',
            ],
            'wallets' => [
                [
                    'id' => 1,
                    'currency_code' => 'USDT',
                    'network_code' => 'tron',
                    'available_balance' => '300.000000',
                    'locked_balance' => '0.000000',
                    'active_address' => 'TXYZ...',
                    'status' => 'active',
                ],
            ],
            'recent_deposits' => [],
            'recent_withdrawals' => [],
        ];
    }
}
