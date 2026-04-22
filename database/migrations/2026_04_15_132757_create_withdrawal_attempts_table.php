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
        Schema::create('withdrawal_attempts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('withdrawal_id')->constrained('withdrawals')->cascadeOnDelete();
            $table->unsignedInteger('attempt_no');

            $table->string('broadcast_fingerprint', 160)->nullable();
            $table->string('status', 30)->default('pending'); // pending | broadcasting | broadcasted | failed | confirmed

            $table->string('txid')->nullable();
            $table->string('broadcast_driver', 50)->nullable();

            $table->string('raw_transaction_hash', 160)->nullable();
            $table->longText('raw_transaction')->nullable();

            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->text('error_message')->nullable();

            $table->timestamp('broadcasted_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            $table->timestamps();

            $table->unique(['withdrawal_id', 'attempt_no'], 'uniq_withdrawal_attempt_no');
            $table->index(['withdrawal_id', 'status'], 'idx_withdrawal_attempts_status');
            $table->index(['broadcast_fingerprint'], 'idx_withdrawal_attempts_fingerprint');
            $table->index(['txid'], 'idx_withdrawal_attempts_txid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('withdrawal_attempts');
    }
};
