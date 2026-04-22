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
        Schema::create('block_scanners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('network_id')->constrained('networks');
            $table->string('last_scanned_block_hash')->nullable();
            $table->unsignedBigInteger('last_scanned_block')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('block_scanners');
    }
};
