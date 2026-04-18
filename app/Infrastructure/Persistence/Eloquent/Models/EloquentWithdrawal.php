<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

class EloquentWithdrawal extends Model
{
    protected $table = 'withdrawals';

    protected $casts = [
        'metadata' => 'array',
        'requested_at' => 'datetime',
        'reserve_at' => 'datetime',
        'broadcasted_at' => 'datetime',
        'settled_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'finalized_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'failed_at' => 'datetime',
        'released_at' => 'datetime',

        'reorged_at' => 'datetime',
        'reversed_at' => 'datetime',
        'reversal_failed_at' => 'datetime',

        'block_number' => 'integer',
        'confirmations' => 'integer',
        'amount' => 'string',
    ];
}


