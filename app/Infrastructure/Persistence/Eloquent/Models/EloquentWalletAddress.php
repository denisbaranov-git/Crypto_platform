<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

class EloquentWalletAddress extends Model
{
    protected $table = 'wallet_addresses';

    protected $fillable = [
        'wallet_id',
        'network_id',
        'currency_network_id',
        'address',
        'derivation_chain',
        'derivation_index',
        'derivation_path',
        'status',
        'is_active',
    ];
    public function wallet()
    {
        return $this->belongsTo(EloquentWallet::class);
    }
}
