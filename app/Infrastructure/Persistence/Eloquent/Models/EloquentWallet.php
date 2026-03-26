<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use App\Domain\Wallet\Entities\WalletAddress;
use App\Models\EloquentCurrencyNetwork;
use App\Models\EloquentWalletAddress;
use Illuminate\Database\Eloquent\Model;

class EloquentWallet extends Model
{
    protected $table = 'wallets';
//$table->id();
//$table->foreignId('user_id')->constrained();
//    //$table->foreignId('network_id')->constrained();// denormalization denis!!!//????
//$table->foreignId('currency_network_id')->constrained('currency_networks');
//$table->string('status')->default('active');//active,locked,archived
//    // Следующий индекс для HD-деривации адреса
//    //$table->unsignedBigInteger('next_address_index')->default(0);
//    // Удобный указатель на текущий активный адрес
//$table->foreignId('active_address_id')->nullable()->constrained('wallet_addresses')->nullOnDelete();
//$table->timestamps();
    protected $fillable = [

    ];

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(EloquentUser::class);
    }
    public function addresses(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EloquentWalletAddress::class);
    }
    public function currencyNetwork(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(EloquentCurrencyNetwork::class);
    }
    public function activeAddress(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(EloquentWalletAddress::class, 'active_address_id');

    }
}
