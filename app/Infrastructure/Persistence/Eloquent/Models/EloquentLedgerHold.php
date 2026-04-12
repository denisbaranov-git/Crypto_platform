<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

final class EloquentLedgerHold extends Model
{
    protected $table = 'ledger_holds';

    protected $fillable = [
        'ledger_operation_id',
        'account_id',
        'currency_network_id',
        'amount',
        'status',
        'reason',
        'expires_at',
        'released_at',
        'consumed_at',
        'metadata',
    ];

    protected $casts = [
        'account_id' => 'integer',
        'currency_network_id' => 'integer',
        'amount' => 'string',
        'metadata' => 'array',
        'expires_at' => 'datetime',
        'released_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];
}
