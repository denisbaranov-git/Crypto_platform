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
            $table->uuid('ledger_operation_id')->nullable();
            $table->foreign('ledger_operation_id')->references('id')->on('ledger_operations')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('currency_network_id')->constrained('currency_networks')->cascadeOnDelete();
            $table->enum('direction', ['debit', 'credit']);
            $table->decimal('amount', 40, 18);
            $table->decimal('balance_before', 40, 18);
            $table->decimal('balance_after', 40, 18);
            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('status', 30)->default('confirmed'); // confirmed | cancelled | reversed
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'created_at'], 'idx_account_transactions_account_created');
            $table->index(['ledger_operation_id'], 'idx_account_transactions_operation');
            $table->index(['reference_type', 'reference_id'], 'idx_account_transactions_reference');
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
