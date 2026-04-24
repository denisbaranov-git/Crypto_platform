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
        Schema::create('withdrawals', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('network_id')->constrained()->cascadeOnDelete();
            $table->foreignId('currency_network_id')->constrained('currency_networks')->cascadeOnDelete();

            $table->string('destination_address', 255);
            $table->string('destination_tag', 255)->nullable();

            $table->decimal('amount', 40, 18);
            $table->decimal('fee_amount', 40, 18)->default(0);
            $table->decimal('network_fee_estimated_amount', 40, 18)->nullable();
            $table->decimal('network_fee_actual_amount', 40, 18)->nullable();
            $table->foreignId('network_fee_currency_network_id')->nullable()->constrained('currency_networks')->nullOnDelete();
            $table->timestamp('network_fee_posted_at')->nullable();
            $table->decimal('total_debit_amount', 40, 18);

            $table->foreignId('fee_rule_id')->nullable()->constrained('fee_rules')->nullOnDelete();
            $table->json('fee_snapshot')->nullable();

            $table->foreignId('ledger_hold_id')->nullable()->constrained('ledger_holds')->nullOnDelete();
            $table->uuid('reserve_operation_id')->nullable();
            $table->uuid('consume_operation_id')->nullable();
            $table->uuid('release_operation_id')->nullable();
            $table->uuid('reversal_operation_id')->nullable();

            $table->foreignId('system_wallet_id')->nullable()->constrained('system_wallets')->nullOnDelete();
            $table->unsignedBigInteger('broadcast_nonce')->nullable();

            $table->string('txid')->nullable();
            $table->unsignedSmallInteger('broadcast_attempts')->default(0);

            $table->string('status', 30)->default('requested');
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('reserved_at')->nullable();
            $table->timestamp('broadcasted_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('reorged_at')->nullable();
            $table->timestamp('reversed_at')->nullable();

            $table->string('failure_reason', 100)->nullable();
            $table->string('cancellation_reason', 100)->nullable();
            $table->string('rejection_reason', 100)->nullable();
            $table->string('reorg_reason', 100)->nullable();
            $table->string('reversal_reason', 100)->nullable();

            $table->text('last_error')->nullable();

            $table->unsignedBigInteger('confirmed_block_number')->nullable();
            $table->string('confirmed_block_hash', 255)->nullable();
            $table->unsignedInteger('confirmed_confirmations')->nullable();

            $table->unsignedBigInteger('reorg_block_number')->nullable();
            $table->unsignedInteger('reversal_attempts')->default(0);
            $table->text('reversal_last_error')->nullable();
            $table->timestamp('reversal_failed_at')->nullable();

            $table->string('idempotency_key', 120)->unique();
            $table->unsignedBigInteger('version')->default(0);

            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['network_id', 'txid'], 'uniq_withdrawals_network_txid');
            $table->index(['user_id', 'status'], 'idx_withdrawals_user_status');
            $table->index(['currency_network_id', 'status'], 'idx_withdrawals_asset_status');
            $table->index(['network_id', 'status'], 'idx_withdrawals_network_status');
            $table->index(['ledger_hold_id'], 'idx_withdrawals_hold');
            $table->index(['system_wallet_id'], 'idx_withdrawals_system_wallet');
            $table->index(['status', 'updated_at'], 'idx_withdrawals_status_updated');
            $table->index(['confirmed_block_number'], 'idx_withdrawals_confirmed_block_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('withdrawals');
    }
};
