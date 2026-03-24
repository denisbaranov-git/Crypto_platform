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
        Schema::create('hd_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('network_id')->constrained('networks');
            $table->string('xpub');
            $table->unsignedBigInteger('next_index')->default(0);
            $table->timestamps();

            $table->unique(['network_id'], 'unique_hd_wallet_per_network');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hd_wallets');
    }
};
