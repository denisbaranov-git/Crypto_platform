<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

final class EloquentConfirmationRule extends Model
{
    protected $table = 'confirmation_rules';

    protected $fillable = [
        'currency_network_id',
        'amount_threshold',
        'confirmation_type',
        'confirmations_required',
        'priority',
        'description',
        'is_active',
    ];

    protected $casts = [
        'amount_threshold' => 'string',
        'confirmations_required' => 'integer',
        'priority' => 'integer',
        'is_active' => 'boolean',
    ];
}
