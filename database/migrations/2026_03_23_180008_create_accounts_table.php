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
        Schema::table('accounts', function (Blueprint $table) {
            $table->id();
            // Универсальный owner: user / system / merchant / partner / future loan
            $table->string('owner_type', 50)->default('user');//owner_type: user|system|merchant|partner|treasury|suspense
            $table->unsignedBigInteger('owner_id')->nullable();
            // Для crypto-учёта: USDT ERC20 / USDT TRC20 / BTC / ETH = отдельные балансные единицы
            $table->foreignId('currency_network_id')
                ->nullable()
                ->constrained('currency_networks')
                ->nullOnDelete();
            // Быстрый остаток
            $table->decimal('balance', 40, 18)->default(0);
            // Быстрый агрегат по всем активным holds
            $table->decimal('reserved_balance', 40, 18)->default(0);
            // active / locked / closed
            $table->string('status', 30)->default('active');
            // Защита от гонок при конкурентных обновлениях
            $table->unsignedBigInteger('version')->default(0);
            $table->json('metadata')->nullable();

            $table->index(['owner_type', 'owner_id'], 'idx_accounts_owner');
            $table->index(['currency_network_id', 'status'], 'idx_accounts_asset_status');
            //unique index на (owner_type, owner_id, currency_network_id)
            $table->unique(['owner_type', 'owner_id', 'currency_network_id'],'uniq_account_owner_asset'); //accounts(user_id, currency_network_id) UNIQUE
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
