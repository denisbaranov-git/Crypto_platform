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
        Schema::create('wallet_addresses', function (Blueprint $table) {

            $table->id();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('network_id')->constrained();
            $table->foreignId('currency_network_id')->constrained('currency_networks');
            $table->string('address', 255);
            $table->unsignedBigInteger('derivation_index');
            $table->string('derivation_path', 255);
            $table->string('status')->default('active');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            //Уникальность адреса в сети
            $table->unique(['network_id', 'address']);
            // индекс уникален в рамках сети
            $table->unique(['network_id', 'derivation_index']);
            // индексы
            $table->index(['wallet_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_addresses');
    }
};
