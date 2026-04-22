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
        Schema::create('system_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('network_id');
            $table->string('address');
            $table->string('type');// тип кошелька type: hot/ cold/ sweep/ fee
            $table->string('encrypted_private_key');
            $table->unsignedBigInteger('current_nonce')->default(0);
            $table->string('status')->default('active'); //статус active disabled
            $table->timestamps();

            $table->unique(['network_id', 'address'], 'uniq_system_wallet_network_address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_wallets');
    }
};
