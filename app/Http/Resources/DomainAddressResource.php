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
//private ?WalletAddressId $id = null;
//private WalletAddressValue $address;
//
//private int $derivationChain;
//
//private int $derivationIndex;
//private DerivationPath $derivationPath;
//private bool $isActive = true;
//private string $status = 'active';
        //return parent::toArray($request);
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
