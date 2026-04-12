<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

final class EloquentLedgerOperation extends Model
{
    protected $table = 'ledger_operations';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'idempotency_key',
        'type',
        'reference_type',
        'reference_id',
        'status',
        'description',
        'metadata',
        'posted_at',
        'failed_at',
    ];

    protected $casts = [
        'reference_id' => 'integer',
        'metadata' => 'array',
        'posted_at' => 'datetime',
        'failed_at' => 'datetime',
    ];
}
