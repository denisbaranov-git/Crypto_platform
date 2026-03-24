<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            //$table->foreignId('network_id')->constrained();// denormalization denis!!!//????
            $table->foreignId('currency_network_id')->constrained('currency_networks');
            $table->string('status')->default('active');//active,locked,archived
            // Следующий индекс для HD-деривации адреса
            //$table->unsignedBigInteger('next_address_index')->default(0);
            // Удобный указатель на текущий активный адрес
            $table->foreignId('active_address_id')->nullable()->constrained('wallet_addresses')->nullOnDelete();
            $table->timestamps();

            // Один кошелёк на пользователя в рамках пары сеть+валюта
            $table->unique(['user_id', 'currency_network_id'], 'unique_user_currency_network');
            $table->index(['user_id', 'status']);
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
