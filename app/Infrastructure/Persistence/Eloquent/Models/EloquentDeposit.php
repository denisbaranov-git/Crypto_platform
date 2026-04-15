<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

final class EloquentDeposit extends Model
{
    protected $table = 'deposits';

    protected $fillable = [
        'user_id',
        'currency_id',
        'network_id',
        'currency_network_id',
        'wallet_address_id',
        'external_key',
        'txid',
        'from_address',
        'to_address',
        'amount',
//        'asset_type',
//        'contract_address',
        'block_hash',
        'block_number',
        'confirmations',
        'status',
        'detected_at',
        'confirmed_at',
        'credited_at',
        'finalized_at',
        'failed_at',
        'failure_reason',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'detected_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'credited_at' => 'datetime',
        'finalized_at' => 'datetime',
        'failed_at' => 'datetime',
        'block_number' => 'integer',
        'confirmations' => 'integer',
        'amount' => 'string',
    ];
}
