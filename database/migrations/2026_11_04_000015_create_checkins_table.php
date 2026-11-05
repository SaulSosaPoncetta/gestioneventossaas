<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Registro de TODOS los escaneos en puerta (válidos y rechazados)
        Schema::create('checkins', function (Blueprint $table) {
            $table->id();
            // Generado por el dispositivo: evita duplicados al sincronizar escaneos offline
            $table->uuid('client_uuid')->nullable()->unique();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // operador
            $table->string('scanned_code', 255);
            $table->enum('result', ['ok', 'already_used', 'invalid', 'wrong_event', 'cancelled']);
            $table->string('gate', 40)->nullable();
            $table->string('device', 80)->nullable();
            $table->boolean('offline')->default(false);
            $table->timestamp('scanned_at');
            $table->timestamps();

            $table->index(['event_id', 'scanned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkins');
    }
};
