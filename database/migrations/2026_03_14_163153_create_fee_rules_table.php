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
        Schema::create('fee_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('currency_network_id')->constrained();
            $table->decimal('min_amount', 40, 18)->nullable();
            $table->decimal('max_amount', 40, 18)->nullable();
            $table->decimal('fee', 40, 18);
            $table->enum('fee_type', ['fixed', 'percent']);
            $table->integer('priority')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fee_rules');
    }
};
