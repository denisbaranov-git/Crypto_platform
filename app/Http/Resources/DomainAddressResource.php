<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DomainAddressResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {

        return [
            'id' => $this->id()?->value(),
            'address' => $this->address()->value(),
            'derivationChain' => $this->derivationChain(),
            'derivationIndex' => $this->derivationIndex(),
            'derivationPath' => $this->derivationPath()->value(),
            'isActive' => $this->isActive(),
            'status' => $this->status(),
        ];
    }
}
