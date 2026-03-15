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
        Schema::create('currency_networks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('network_id')->constrained()->onDelete('cascade');
            $table->foreignId('currency_id')->constrained()->onDelete('cascade');

            // Основные параметры токена в данной сети
            $table->tinyInteger('decimals')->unsigned()->comment('Количество знаков после запятой (6, 8, 18)');
            $table->string('contract_address', 255)->nullable()->comment('Адрес контракта (NULL для нативных монет)');

            // Параметры безопасности и лимитов
            $table->integer('min_confirmations')->unsigned()->default(12)->comment('Минимальное количество подтверждений');
            $table->decimal('min_deposit_amount', 40, 18)->default(0)->comment('Минимальная сумма депозита');
            $table->decimal('min_withdrawal_amount', 40, 18)->default(0)->comment('Минимальная сумма вывода');
            $table->decimal('max_withdrawal_amount', 40, 18)->nullable()->comment('Максимальная сумма вывода (NULL = без ограничений)');

            $table->boolean('use_finality')->default(false)->after('min_confirmations')
                ->comment('Использовать финализацию вместо подтверждений?');
            $table->integer('finalization_blocks')->unsigned()->nullable()->after('use_finality')
                ->comment('Количество блоков до финализации (если use_finality=true)');
            $table->decimal('finality_threshold', 40, 18)->nullable()->after('finalization_blocks')
                ->comment('Минимальная сумма для применения финализации');
            // Комиссии вынесли в fee_rules!!!!!!!!denis
            $table->decimal('withdrawal_fee', 40, 18)->default(0)->comment('Комиссия за вывод');
            $table->enum('withdrawal_fee_type', ['fixed', 'percent'])->default('fixed')->comment('Тип комиссии (фикс/процент)');
            // Статусы
            $table->boolean('is_active')->default(true)->comment('Активна ли пара сеть-валюта');
            $table->boolean('is_deposit_enabled')->default(true)->comment('Разрешены ли депозиты');
            $table->boolean('is_withdrawal_enabled')->default(true)->comment('Разрешены ли выводы');

            // Дополнительно
            $table->integer('sort_order')->default(0)->comment('Порядок сортировки');
            $table->json('metadata')->nullable()->comment('Дополнительные данные');

            $table->timestamps();

            $table->unique(['network_id', 'currency_id'], 'unique_network_currency');
            // Индексы для поиска
            $table->index('contract_address');
            $table->index('is_active');
            $table->index(['is_deposit_enabled', 'is_withdrawal_enabled']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('currency_networks');
    }
};
