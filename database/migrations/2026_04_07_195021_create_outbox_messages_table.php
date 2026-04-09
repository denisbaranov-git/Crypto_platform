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
        Schema::create('outbox_messages', function (Blueprint $table) {
            $table->id();

            // Уникальность сообщения: одинаковое событие не должно попасть в outbox дважды.
            $table->string('idempotency_key')->unique();

            $table->string('aggregate_type', 100);
            $table->string('aggregate_id', 100);
            $table->string('event_type', 200);

            $table->json('payload');

            // pending -> processing -> dispatched | failed
            $table->string('status')->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);

            // Можно отложить повторную попытку.
            $table->timestamp('available_at')->nullable();

            // Когда воркер взял сообщение в работу.
            $table->timestamp('locked_at')->nullable();

            // Когда сообщение успешно обработано downstream.
            $table->timestamp('dispatched_at')->nullable();

            $table->text('last_error')->nullable();

            $table->timestamps();

            $table->index(['status', 'available_at']);
            $table->index(['aggregate_type', 'aggregate_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('outbox_messages');
    }
};
