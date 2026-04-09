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
        Schema::create('network_scanner_cursors', function (Blueprint $table) {
            $table->id();

            $table->foreignId('network_id')->constrained()->cascadeOnDelete();

            // Последний ПОЛНОСТЬЮ обработанный блок.
            $table->unsignedBigInteger('last_processed_block')->default(0);

            // Хэш последнего обработанного блока.
            // Он нужен, чтобы быстро понять, что последний блок был откатан reorg'ом.
            $table->string('last_processed_block_hash')->nullable();

            // Когда сканировали сеть в последний раз.
            $table->timestamp('scanned_at')->nullable();

            // Сюда можно складывать небольшую историю или отладочные данные.
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->unique('network_id', 'uniq_scanner_cursor_network');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('network_scanner_cursors');
    }
};
