<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        //return parent::toArray($request);
        return [
            'id' => $this->id,
            'currency_code' => $this->currency_code,
            'network_code' => $this->network_code,
            'available_balance' => $this->balance,
            'locked_balance' => $this->reserved_balance,
            'active_address' => $this->address,
            'status' => $this->status
        ];
    }
}
