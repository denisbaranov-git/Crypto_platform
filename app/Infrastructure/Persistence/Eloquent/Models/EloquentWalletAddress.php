<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

class EloquentWalletAddress extends Model
{
    protected $table = 'wallet_addresses';
//    $table->id();
//    $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
//    $table->foreignId('network_id')->constrained();
//    $table->string('address', 255);
//    $table->unsignedBigInteger('derivation_index');
//    $table->string('derivation_path', 255);
//    $table->string('status')->default('active');
//    $table->boolean('is_active')->default(true);
//    $table->timestamps();
    protected $fillable = [
        'wallet_id',
        'network_id',
        'address',
        'derivation_index',
        'status',
        'address',
        'is_active'
    ];
    public function wallet()
    {
        return $this->belongsTo(EloquentWallet::class);
    }
}
