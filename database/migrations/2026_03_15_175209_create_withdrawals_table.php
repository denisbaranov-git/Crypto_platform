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
            $table->foreignId('user_id');
            $table->foreignId('currency_id');
            $table->foreignId('network_id');
            $table->decimal('amount', 40, 18);
            $table->decimal('fee', 40, 18);
            $table->string('address');// адрес назначения
            $table->string('tag')->nullable();// memo/tag
            $table->string('txid')->nullable();
            /* status статус вывода pending,approved,broadcasted,confirmed,failed */
            $table->string('status')->default('pending');
            $table->timestamps();
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
