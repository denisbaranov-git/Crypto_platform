<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

class EloquentHdWallet extends Model
{
    protected $table = 'hd_wallets';

    protected $fillable = [
        'network_id',
        'encrypted_xpub',
        'next_index',
        'status',
    ];
}
