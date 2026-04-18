<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Mappers;

use App\Domain\Withdrawal\Entities\WithdrawalAttempt;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentWithdrawalAttempt;

final class WithdrawalAttemptMapper
{
    public function toDomain(EloquentWithdrawalAttempt $model): WithdrawalAttempt
    {
        return new WithdrawalAttempt(
            id: (int) $model->id,
            withdrawalId: (int) $model->withdrawal_id,
            attemptNo: (int) $model->attempt_no,
            status: (string) $model->status,
            txid: $model->txid,
            broadcastDriver: $model->broadcast_driver,
            requestPayload: $model->request_payload ?? [],
            responsePayload: $model->response_payload ?? [],
            errorMessage: $model->error_message,
            broadcastedAt: $model->broadcasted_at?->toDateTimeString(),
            confirmedAt: $model->confirmed_at?->toDateTimeString(),
        );
    }

    public function fillModel(EloquentWithdrawalAttempt $model, WithdrawalAttempt $attempt): EloquentWithdrawalAttempt
    {
        $model->fill([
            'withdrawal_id' => $attempt->withdrawalId(),
            'attempt_no' => $attempt->attemptNo(),
            'status' => $attempt->status(),
            'txid' => $attempt->txid(),
            'broadcast_driver' => $attempt->broadcastDriver(),
            'request_payload' => $attempt->requestPayload(),
            'response_payload' => $attempt->responsePayload(),
            'error_message' => $attempt->errorMessage(),
            'broadcasted_at' => $attempt->broadcastedAt(),
            'confirmed_at' => $attempt->confirmedAt(),
        ]);

        return $model;
    }
}
