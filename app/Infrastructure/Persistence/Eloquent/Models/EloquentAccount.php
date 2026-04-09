<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

final class EloquentAccount extends Model
{
    protected $table = 'accounts';

    protected $fillable = [
        'owner_type',
        'owner_id',
        'currency_network_id',
        'balance',
        'reserved_balance',
        'status',
        'version',
        'metadata',
    ];

    protected $casts = [
        'balance' => 'string',
        'reserved_balance' => 'string',
        'metadata' => 'array',
        'version' => 'integer',
        'owner_id' => 'integer',
        'currency_network_id' => 'integer',
    ];
}
