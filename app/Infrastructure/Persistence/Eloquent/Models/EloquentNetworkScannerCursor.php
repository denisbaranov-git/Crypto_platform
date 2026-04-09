<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

final class EloquentNetworkScannerCursor extends Model
{
    protected $table = 'network_scanner_cursors';

    protected $fillable = [
        'network_id',
        'last_processed_block',
        'last_processed_block_hash',
        'scanned_at',
        'metadata',
    ];

    protected $casts = [
        'last_processed_block' => 'integer',
        'scanned_at' => 'datetime',
        'metadata' => 'array',
    ];
}
