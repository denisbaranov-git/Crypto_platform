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
        Schema::create('currencies', function (Blueprint $table) {

            $table->id();
            $table->string('code', 20)->unique(); //(USDT, ETH, BTC, TRX);
            $table->string('name', 100); //(Tether USD, Ethereum,..);
            //$table->enum('type', ['crypto', 'fiat'])->default('crypto');
            $table->string('type', 20)->default('crypto'); //['crypto','fiat', ..]
            $table->string('symbol', 10)->nullable();//('Символ валюты ($, €, ₿);
            $table->string('logo_url', 255)->nullable();//('URL логотипа');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('type');
            $table->index('is_active');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
