<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DomainWalletResource extends JsonResource
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
            'id' => $this->id()?->value(),
            'user_id' => $this->userId()->value(),
            'currency_network_id' => $this->currencyNetworkId()->value(),
            'status' => $this->status()->value,
            'addresses' => DomainAddressResource::collection($this->addresses()),//$this->addresses(),
            'active_address_id' => $this->activeAddressId()?->value(),
        ];
    }
}
