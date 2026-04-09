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
        Schema::create('ledger_operations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Защита от двойного постинга одной и той же бизнес-операции
            $table->string('idempotency_key', 120)->unique();
            // credit, debit, reserve, release, consume, transfer, fee, reversal, adjustment
            $table->string('type', 50);
            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('status', 30)->default('pending'); // pending | posted | failed | reversed
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            $table->timestamps();

            $table->index(['reference_type', 'reference_id'], 'idx_ledger_operations_reference');
            $table->index(['status', 'created_at'], 'idx_ledger_operations_status_created');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ledger_operations');
    }
};
