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
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('network_id')->constrained()->cascadeOnDelete();
            $table->foreignId('currency_network_id')->constrained('currency_networks')->cascadeOnDelete();
            $table->foreignId('wallet_address_id')->constrained('wallet_addresses')->cascadeOnDelete();
            // Уникальный идентификатор внешнего on-chain факта.
            // ETH ERC20: txHash:logIndex
            // ETH native: txHash:0
            // TRON TRC20: txid:index
            // BTC: txid:vout
            $table->string('external_key');
            $table->string('txid');
            $table->string('from_address')->nullable();
            $table->string('to_address');
            $table->decimal('amount', 40, 18);
            $table->string('block_hash')->nullable();
            $table->unsignedBigInteger('block_number')->nullable();
            $table->unsignedInteger('confirmations')->default(0);
            $table->string('status')->default('detected'); // detected|pending|confirmed|credited|reorged|reversed|failed|reversal_failed
            $table->timestamp('detected_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('credited_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            /**
             * Operation IDs.
             * Один депозит может быть credited один раз и reversed один раз.
             */

            $table->string('credited_operation_id', 120)->nullable();
            $table->string('reversal_operation_id', 120)->nullable();

            //////////////////////////////////////////////////this is need throw out to some Reorgs table
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
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->unique(['network_id', 'external_key'], 'uniq_deposit_network_external_key');
            $table->index(['network_id', 'status']);
            $table->index(['wallet_address_id', 'status']);
            $table->index(['network_id', 'block_number']);

            $table->index('credited_operation_id', 'idx_deposits_credited_operation');
            $table->index('reversal_operation_id', 'idx_deposits_reversal_operation');
            $table->index(['network_id', 'status', 'block_number'], 'idx_deposits_network_status_block');
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
