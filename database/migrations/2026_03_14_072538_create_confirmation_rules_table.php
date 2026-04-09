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
        Schema::create('confirmation_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('currency_network_id')->constrained('currency_networks')->onDelete('cascade');

            $table->decimal('amount_threshold', 40, 18)->nullable()->comment('Порог суммы (NULL = для всех сумм)');
            $table->enum('confirmation_type', ['blocks', 'finality'])->default('blocks')
                ->comment('blocks = считать блоки, finality = ждать финализации');
            $table->integer('confirmations_required')->unsigned();//'Требуемое количество подтверждений');
            $table->tinyInteger('priority')->default(0)->comment('Приоритет правила (больше = выше)');

            $table->text('description')->nullable();//'Описание правила');
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['currency_network_id', 'amount_threshold', 'priority'], 'idx_confirmation_rules_lookup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('confirmation_rules');
    }
};
