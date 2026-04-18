<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

class EloquentCurrencyNetwork extends Model
{
    protected $table = 'currency_networks';

    public function network()
    {
        return $this->hasOne(EloquentNetwork::class, 'id', 'network_id');
    }
    public function currency()
    {
        return $this->hasOne(EloquentCurrency::class, 'id', 'currency_id');
    }
}
