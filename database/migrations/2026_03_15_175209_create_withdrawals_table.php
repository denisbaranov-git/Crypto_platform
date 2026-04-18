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

            // Для маршрутизации и хранения факта выбранной сети.
            $table->foreignId('network_id')->constrained()->cascadeOnDelete();

            // Единица учета для crypto — только currency_network_id.
            $table->foreignId('currency_network_id')
                ->constrained('currency_networks')
                ->cascadeOnDelete();

            // Куда отправляем.
            $table->string('destination_address', 255);
            $table->string('destination_tag', 255)->nullable(); // memo/tag для TRX/XRP-like сценариев

            // Суммы.
            // amount = сумма, которая уходит адресату on-chain.
            $table->decimal('amount', 40, 18);
            $table->decimal('fee_amount', 40, 18)->default(0); // platform withdrawal fee
            $table->decimal('network_fee_estimated_amount', 40, 18)->nullable();
            $table->decimal('network_fee_actual_amount', 40, 18)->nullable();
            $table->decimal('total_debit_amount', 40, 18); // amount + fee_amount

            // Снимок fee rules, чтобы не пересчитывать задним числом.
            $table->foreignId('fee_rule_id')->nullable()->constrained('fee_rules')->nullOnDelete();
            $table->json('fee_snapshot')->nullable();

            // Ledger linkage.
            $table->foreignId('ledger_hold_id')->nullable()->constrained('ledger_holds')->nullOnDelete();
            $table->uuid('reserve_operation_id')->nullable();
            $table->uuid('consume_operation_id')->nullable();
            $table->uuid('release_operation_id')->nullable();

            // On-chain execution.
            $table->foreignId('system_wallet_id')->nullable()->constrained('system_wallets')->nullOnDelete();
            ///
            // Уникальный идентификатор внешнего on-chain факта.
            // ETH ERC20: txHash:logIndex
            // ETH native: txHash:0
            // TRON TRC20: txid:index
            // BTC: txid:vout
            $table->string('external_key');
            $table->string('txid')->nullable();
            $table->string('block_hash')->nullable();
            $table->unsignedBigInteger('block_number')->nullable();
            $table->unsignedInteger('confirmations')->default(0);

            $table->unsignedSmallInteger('broadcast_attempts')->default(0);

            // Lifecycle.
            $table->string('status')->default('requested');
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('reserved_at')->nullable();
            $table->timestamp('broadcasted_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('debited_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('released_at')->nullable();

            // Reasons / errors.
            $table->string('failure_reason', 100)->nullable();
            $table->string('cancellation_reason', 100)->nullable();
            $table->string('rejection_reason', 100)->nullable();
            $table->text('last_error')->nullable();

            // Idempotency / recovery.
            $table->string('idempotency_key', 120)->unique();
            $table->unsignedBigInteger('version')->default(0);

            //////////////////////////////////////////////////////////////////// like deposit

            /**
             * Operation IDs.
             * Один widthdrawal  может быть debiteted один раз и reversed один раз.
             */
            $table->string('debited_operation_id', 120)->nullable();
            $table->string('reversal_operation_id', 120)->nullable();

            //////////////////////////////////////// this is need throw out to some Reorgs table
            /**
             * Reorg lifecycle.
             */
            $table->timestamp('reorged_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            /**
             * Причины.
             */
            $table->string('reorg_reason', 100)->nullable();
            $table->string('reversal_reason', 100)->nullable();
            /**
             * Для расследования reorg.
             */
            $table->unsignedBigInteger('reorg_block_number');
            /**
             * Если reversal не удался сразу.
             */
            $table->unsignedInteger('reversal_attempts')->default(0);
            $table->text('reversal_last_error')->nullable();
            $table->timestamp('reversal_failed_at')->nullable();

            $table->string('failure_reason')->nullable();
            ///////////////////////////////////////////////////////////////////////

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->unique(['network_id', 'external_key'], 'uniq_withdrawal_network_external_key');
            $table->index(['user_id', 'status'], 'idx_withdrawals_user_status');
            $table->index(['currency_network_id', 'status'], 'idx_withdrawals_asset_status');
            $table->index(['network_id', 'status'], 'idx_withdrawals_network_status');
            $table->index('debited_operation_id', 'idx_withdrawals_credited_operation');
            $table->index('reversal_operation_id', 'idx_withdrawals_reversal_operation');
            $table->index(['ledger_hold_id'], 'idx_withdrawals_hold');
            $table->index(['system_wallet_id'], 'idx_withdrawals_system_wallet');
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
