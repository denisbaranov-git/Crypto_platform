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
        Schema::create('networks', function (Blueprint $table) {
            $table->id();
            $table->string('name');// Ethereum, Tron, Bitcoin, BSC
            $table->string('code')->unique();// внутренний код сети
            $table->string('type');//Тип сети  evm,utxo,account
            $table->string('chain_id')->nullable();// chain id (например 1 для Ethereum)
            $table->string('rpc_endpoint')->nullable();// RPC endpoint
            $table->unsignedInteger('confirmations_required')->default(12);// сколько подтверждений нужно
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('networks');
    }
};
