<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActivateWalletAddressRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }
    protected function prepareForValidation(): void
    {
        // Добавляем параметры маршрута в данные запроса
        $this->merge([
            'wallet' => $this->route('wallet'),
            'address' => $this->route('address'),
        ]);
    }
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'wallet' => [
                'required',
                Rule::exists('wallets', 'id'),
            ],
            'address' => [
                'required',
                Rule::exists('wallet_addresses', 'id')->where(function ($query) {
                    $query->where('wallet_id', $this->route('wallet'));
                }),
            ],
        ];
    }
}
