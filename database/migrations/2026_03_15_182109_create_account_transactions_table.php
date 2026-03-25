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
        Schema::create('account_transactions', function (Blueprint $table) {
            $table->id();
            // id бизнес операции (например trade_id)
            $table->uuid('operation_id'); // referens по type - источник операции  бизнес-источник операции
            $table->foreignId('user_id');
            $table->foreignId('currency_id');
            /* тип операции  deposit,withdrawal,trade_buy,trade_sell,fee,transfer_in,transfer_out,adjustment */
            $table->string('type');
            /*  источник операции deposit,withdrawal,trade,transfer,fiat_payment,system */
            $table->string('reference_type')->nullable(); //Это бизнес-источник операции.
            $table->unsignedBigInteger('reference_id')->nullable();
            // сумма операции (+ или -)
            $table->decimal('amount', 40, 18);
            // баланс до операции
            $table->decimal('balance_before', 40, 18);
            // баланс после операции
            $table->decimal('balance_after', 40, 18);
            /*  статус  pending,confirmed,cancelled  */
            $table->string('status')->default('pending');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id','currency_id','created_at']);
            $table->index('operation_id');
            $table->index(['reference_type','reference_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_transactions');
    }
};
