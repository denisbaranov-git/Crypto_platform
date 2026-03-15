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
            $table->foreignId('network_id')->constrained();
            $table->foreignId('currency_network_id')->constrained('currency_network');

            $table->string('address', 255);
            //$table->decimal('balance', 40, 18)->default(0);
            // доступный баланс
            $table->decimal('available_balance', 40, 18)->default(0);
            // заблокированный баланс
            // ордера / вывод
            $table->decimal('locked_balance', 40, 18)->default(0);
            $table->decimal('total_balance', 40, 18)->virtualAs('available_balance + locked_balance');
            $table->timestamps();

            // Уникальность адреса в рамках сети
            $table->unique(['network_id', 'address'], 'unique_address_per_network');
            // У пользователя только один кошелёк на валюту в сети
            $table->unique(['user_id', 'network_currency_id'], 'unique_user_currency');
            // Индексы
            $table->index('address');
            $table->index(['user_id', 'network_id']);
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
