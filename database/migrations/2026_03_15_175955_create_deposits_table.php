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
        Schema::create('deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->foreignId('currency_id');
            $table->foreignId('network_id');
            $table->foreignId('wallet_address_id');
            $table->string('txid');// tx hash
            $table->unsignedInteger('log_index')->nullable();// log index (для EVM)
            $table->string('from_address')->nullable(); // денормализация !! denis
            $table->string('to_address');
            $table->decimal('amount', 40, 18);
            $table->string('block_hash')->nullable();
            $table->unsignedBigInteger('block_number')->nullable();
            $table->unsignedInteger('confirmations')->default(0);
            /* статус депозита  detected,pending,confirmed,credited,failed */
            $table->string('status')->default('detected');
            $table->timestamps();

            $table->unique(['txid', 'log_index']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deposits');
    }
};
