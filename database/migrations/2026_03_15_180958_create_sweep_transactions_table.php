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
        Schema::create('sweep_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('currency_id');
            $table->foreignId('network_id');
            $table->unsignedBigInteger('from_wallet_id');
            $table->unsignedBigInteger('to_wallet_id');
            $table->decimal('amount', 40, 18);
            $table->string('txid')->nullable();
            $table->string('block_hash')->nullable();
            $table->unsignedBigInteger('block_number')->nullable();
            $table->unsignedInteger('confirmations')->default(0);
            /* статус sweep  pending,broadcasted,confirmed */
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sweep_transactions');
    }
};
