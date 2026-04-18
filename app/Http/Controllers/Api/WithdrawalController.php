<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Withdrawal\Commands\CancelWithdrawalCommand;
use App\Application\Withdrawal\Handlers\CancelWithdrawalHandler;
use App\Application\Withdrawal\Handlers\RequestWithdrawalHandler;
use App\Http\Requests\RequestWithdrawalRequest;
//use App\Jobs\BroadcastWithdrawalJob;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentWithdrawal;
use App\Infrastructure\Withdrawal\Jobs\BroadcastWithdrawalJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class WithdrawalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->id;

        $query = EloquentWithdrawal::query()
            ->where('user_id', $userId)
            ->orderByDesc('id');

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        return response()->json(
            $query->paginate(min((int) $request->integer('per_page', 20), 100))
        );
    }

    public function show(Request $request, int $withdrawal): JsonResponse
    {
        $userId = (int) $request->user()->id;

        $model = EloquentWithdrawal::query()
            ->where('id', $withdrawal)
            ->where('user_id', $userId)
            ->firstOrFail();

        return response()->json($model);
    }

    public function store(
        RequestWithdrawalRequest $request,
        RequestWithdrawalHandler $handler
    ): JsonResponse {
        $withdrawalId = $handler->handle( //new RequestWithdrawalCommand
            $request->toCommand((int) $request->user()->id)  //<<<---idempotencyKey: (string) $this->input('idempotency_key'),!!!!!
        );

        // CHANGED: after the DB transaction is finished, kick off broadcast.
        dispatch(new BroadcastWithdrawalJob($withdrawalId));

        return response()->json(
            EloquentWithdrawal::query()->findOrFail($withdrawalId),
            201
        );
    }

    public function cancel(
        Request $request,
        int $withdrawal,
        CancelWithdrawalHandler $handler
    ): JsonResponse {
        $handler->handle(new CancelWithdrawalCommand(
            withdrawalId: $withdrawal,
            reason: (string) $request->input('reason', 'user_request'),
            metadata: (array) $request->input('metadata', [])
        ));

        return response()->json(['status' => 'ok']);
    }
}
