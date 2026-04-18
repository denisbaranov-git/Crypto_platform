<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

final class EloquentFeeRule extends Model
{
    protected $table = 'fee_rules';

    protected $fillable = [
        'currency_network_id',
        'min_amount',
        'max_amount',
        'fee',
        'fee_type',
        'priority',
    ];
}
