<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Application\Withdrawal\Commands\RequestWithdrawalCommand;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentCurrencyNetwork;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class RequestWithdrawalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    protected function prepareForValidation(): void
    {
        $idempotencyKey = $this->header('Idempotency-Key') ?? $this->input('idempotency_key');

        $this->merge([
            'idempotency_key' => is_string($idempotencyKey) ? trim($idempotencyKey) : $idempotencyKey,
            'destination_tag' => $this->filled('destination_tag') ? trim((string) $this->input('destination_tag')) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'network_id' => ['required', 'integer', 'exists:networks,id'],
            'currency_network_id' => ['required', 'integer', 'exists:currency_networks,id'],
            'destination_address' => ['required', 'string', 'max:255'],
            'destination_tag' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'string', 'regex:/^\d+(\.\d{1,18})?$/'],
            'idempotency_key' => ['required', 'string', 'max:120', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $networkId = (int) $this->input('network_id');
            $currencyNetworkId = (int) $this->input('currency_network_id');

            $pairExists = EloquentCurrencyNetwork::query()
                ->whereKey($currencyNetworkId)
                ->where('network_id', $networkId)
                ->exists();

            if (! $pairExists) {
                $validator->errors()->add(
                    'currency_network_id',
                    'Selected currency_network_id does not belong to selected network_id.'
                );
            }
        });
    }

    public function toCommand(int $userId): RequestWithdrawalCommand
    {
        return new RequestWithdrawalCommand(
            userId: $userId,
            networkId: (int) $this->integer('network_id'),
            currencyNetworkId: (int) $this->integer('currency_network_id'),
            destinationAddress: (string) $this->input('destination_address'),
            destinationTag: $this->filled('destination_tag') ? (string) $this->input('destination_tag') : null,
            amount: (string) $this->input('amount'),
            idempotencyKey: (string) $this->input('idempotency_key'),
            metadata: (array) $this->input('metadata', []),
        );
    }
}
