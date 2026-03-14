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
            $table->string('code', 20)->unique(); //BTC, ETH, USDT, USD
            $table->string('name', 100);
            //$table->enum('type',['crypto','fiat']);
            $table->string('type', 20); //['crypto','fiat']
            $table->string('logo_url');
            $table->timestamps();

//            $table->id();
//            $table->string('code', 20)->unique()->comment('Код валюты (USDT, ETH, BTC, TRX)');
//            $table->string('name', 100)->comment('Полное название (Tether USD, Ethereum)');
//            $table->enum('type', ['crypto', 'fiat'])->default('crypto')->comment('Тип валюты');
//            $table->string('symbol', 10)->nullable()->comment('Символ валюты ($, €, ₿)');
//            $table->string('logo_url', 255)->nullable()->comment('URL логотипа');
//            $table->integer('sort_order')->default(0)->comment('Порядок сортировки');
//            $table->boolean('is_active')->default(true)->comment('Активна ли валюта');
//            $table->json('metadata')->nullable()->comment('Дополнительные данные');
//            $table->timestamps();
//
//            $table->index('type');
//            $table->index('is_active');

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
