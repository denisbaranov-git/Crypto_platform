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

            'network_id' => ['required', 'integer', 'exists:networks,id'],
            'network_code' => ['required', 'string', 'exists:networks,code'],
            'currency_network_id' => ['required', 'integer', 'exists:currency_networks,id'],
        ];
    }
}
