<?php

namespace App\Http\Controllers\Api;

use App\Application\Deposit\Queries\GetDepositDetailsQuery;
use App\Application\Deposit\Queries\GetUserDepositsQuery;
use App\Application\Deposit\QueryHandlers\GetDepositDetailsHandler;
use App\Application\Deposit\QueryHandlers\GetUserDepositsHandler;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DepositController extends Controller
{
    public function index( Request $request, GetUserDepositsHandler $handler, ): JsonResponse
    {
        $query = new GetUserDepositsQuery(
            userId: $request->user()->id,
            status: $request->input('status'),
            currency: $request->input('currency'),
            network: $request->input('network'),
            page: (int)$request->input('page', 1),
            perPage: min(max((int)$request->input('per_page', 20), 1),100),
        );

        $result = $handler->handle($query);

        return response()->json($result);
    }

    public function show( int $deposit, Request $request, GetDepositDetailsHandler $handler,): JsonResponse
    {
        $query = new GetDepositDetailsQuery(
            userId: $request->user()->id,
            depositId: $deposit,
        );

        $result = $handler->handle($query);

        return response()->json($result);
    }
}
