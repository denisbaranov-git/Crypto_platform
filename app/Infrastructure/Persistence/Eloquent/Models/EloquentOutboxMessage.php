<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

final class EloquentOutboxMessage extends Model
{
    protected $table = 'outbox_messages';

    protected $fillable = [
        'idempotency_key',
        'aggregate_type',
        'aggregate_id',
        'event_type',
        'payload',
        'status',
        'attempts',
        'available_at',
        'locked_at',
        'dispatched_at',
        'last_error',
    ];

    protected $casts = [
        'payload' => 'array',
        'available_at' => 'datetime',
        'locked_at' => 'datetime',
        'dispatched_at' => 'datetime',
        'attempts' => 'integer',
    ];
}
