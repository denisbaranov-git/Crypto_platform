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
        Schema::create('fiat_crypto_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->foreignId('fiat_payment_id');
            $table->foreignId('order_id')->nullable();
            $table->decimal('fiat_amount', 20, 2);
            $table->foreignId('crypto_currency_id');
            $table->decimal('crypto_amount', 40, 18);
            $table->decimal('rate', 40, 18);
            $table->decimal('fee', 40, 18);
            /*  статус  pending,completed,failed */
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fiat_crypto_conversions');
    }
};
