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
        Schema::create('ledger_holds', function (Blueprint $table) {
            $table->id();
            $table->uuid('ledger_operation_id');
            $table->foreign('ledger_operation_id')
                ->references('id')
                ->on('ledger_operations')
                ->cascadeOnDelete();
            $table->foreignId('account_id')
                ->constrained('accounts')
                ->cascadeOnDelete();
            $table->foreignId('currency_network_id')
                ->constrained('currency_networks')
                ->cascadeOnDelete();
            $table->decimal('amount', 40, 18);
            $table->string('status', 30)->default('active'); // active | released | consumed | expired
            $table->string('reason', 50)->nullable(); // withdrawal | aml | dispute | collateral | manual
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'status'], 'idx_ledger_holds_account_status');
            $table->index(['ledger_operation_id'], 'idx_ledger_holds_operation');
            $table->index(['expires_at', 'status'], 'idx_ledger_holds_expiry');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ledger_holds');
    }
};
