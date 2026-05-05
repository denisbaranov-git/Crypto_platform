<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

class EloquentSystemWallet extends Model
{
    protected $table = 'system_wallets';
    protected $fillable = [
                'network_id',
                'address',
                'encrypted_private_key',
                'type',
                'status',
            ];
}
