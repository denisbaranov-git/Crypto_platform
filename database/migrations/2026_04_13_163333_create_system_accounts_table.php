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
        //clearing — промежуточный/расчётный счёт;
        //fee_income — доход платформы;
        //treasury — казначейство;
        //suspense — временный счёт для разборов;
        //hot_wallet — если хочешь отражать ledger mirror горячего кошелька.

        Schema::create('system_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();   // clearing, fee_income, treasury, suspense, hot_wallet
            $table->string('name', 100);
            $table->string('purpose', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_accounts');
    }
};
