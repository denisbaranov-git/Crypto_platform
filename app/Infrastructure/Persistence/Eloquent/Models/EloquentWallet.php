<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

class EloquentWallet extends Model
{
    protected $table = 'wallets';

    protected $fillable = [
        'user_id',
        'currency_network_id',
        'status',
        'active_address_id'
    ];

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(EloquentUser::class);
    }
    public function addresses(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EloquentWalletAddress::class, 'wallet_id');
        //return $this->hasMany(EloquentWalletAddress::class);
    }
    public function currencyNetwork(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(EloquentCurrencyNetwork::class);
    }
    public function activeAddress(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(EloquentWalletAddress::class, 'active_address_id');

    }
}
