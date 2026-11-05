<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bitácora de notificaciones recibidas (webhooks) para procesarlas una sola vez
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 20)->default('mercadopago');
            $table->string('request_id', 100)->nullable(); // header x-request-id
            $table->string('topic', 50);
            $table->string('external_id', 100);
            $table->json('payload');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'request_id']);
            $table->index(['topic', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
