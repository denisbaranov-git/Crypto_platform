<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreateAddressRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'network_id' => ['required', 'integer', 'exists:networks,id'],
            'network_code' => ['required', 'string', 'exists:networks,code'],
            'currency_network_id' => ['required', 'integer', 'exists:currency_networks,id'],
        ];
    }
}
