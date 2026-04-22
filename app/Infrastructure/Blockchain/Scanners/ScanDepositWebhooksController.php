<?php

namespace App\Infrastructure\Blockchain\Scanners;

use App\Application\Deposit\Commands\RegisterDetectedDepositCommand;
use App\Application\Deposit\Handlers\RegisterDetectedDepositHandler;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class ScanDepositWebhooksController extends Controller
{
    public function __invoke(Request $request, RegisterDetectedDepositHandler $handler)
    {
        // 1) verify provider signature
        // 2) decode payload
        // 3) map to command
        // 4) pass to handler

        $handler->handle(new RegisterDetectedDepositCommand(
            userId: (int) $request->input('user_id'),
            networkId: (int) $request->input('network_id'),
            currencyNetworkId: (int) $request->input('currency_network_id'),
            walletAddressId: (int) $request->input('wallet_address_id'),
            externalKey: (string) $request->input('external_key'),
            txid: (string) $request->input('txid'),
            amount: (string) $request->input('amount'),
            toAddress: (string) $request->input('to_address'),
            fromAddress: $request->input('from_address'),
            blockHash: $request->input('block_hash'),
            blockNumber: $request->input('block_number') ? (int) $request->input('block_number') : null,
            confirmations: (int) $request->input('confirmations', 0),
            metadata: (array) $request->input('metadata', []),
        ));

        return response()->json(['ok' => true]);
    }
}
