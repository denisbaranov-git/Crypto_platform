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
            $table->string('name', 100);//(Ethereum, BNB Chain, Tron)');
            $table->string('code', 20)->unique();//'Код сети для API (ethereum, bsc, tron)');
            $table->integer('chain_id')->nullable();//Chain ID для EVM сетей (1, 56, 137)');
            $table->string('native_currency_code', 10);//(ETH, BNB, TRX)');
            $table->string('native_currency_name', 50)->nullable();//'Название нативной валюты');
            $table->boolean('is_testnet')->default(false);//'Тестовая сеть или основная');
            $table->string('explorer_url', 255)->nullable();//('URL блокчейн-эксплорера');
            $table->string('rpc_url', 255)->nullable();//('RPC URL (опционально, можно хранить в .env)');
            $table->string('rpc_driver', 255)->nullable();//(evm, tron, bitcoin);
            $table->json('metadata')->nullable();//('Дополнительные данные');
            $table->timestamps();

            $table->index('code');
            $table->index('is_testnet');
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
