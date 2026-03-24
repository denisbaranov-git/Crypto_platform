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
            //fiat_payments
            //-------------
            //id
            //user_id
            //provider
            //amount
            //currency
            //status
            //created_at
        Schema::create('fiat_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            // платежный провайдер
            // Stripe / Adyen / Moonpay
            $table->string('provider');
            // id платежа у провайдера
            $table->string('provider_payment_id');
            $table->string('currency'); // USD
            $table->decimal('amount', 20, 2);
            /* статус платежа  pending,confirmed,failed */
            $table->string('status')->default('pending');
            $table->timestamp('confirmed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fiat_payments');
    }
};
