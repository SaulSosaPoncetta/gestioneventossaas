<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Una fila por entrada emitida (lleva el QR)
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('code', 16)->unique(); // código corto público (va dentro del QR firmado)
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('event_seat_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('holder_user_id')->nullable()->constrained('users')->nullOnDelete();

            // Datos del asistente (obligatorios si el evento es nominativo)
            $table->string('attendee_name')->nullable();
            $table->string('attendee_dni', 15)->nullable();
            $table->string('attendee_email')->nullable();

            $table->enum('status', ['valid', 'used', 'cancelled'])->default('valid');
            $table->timestamp('checked_in_at')->nullable();
            $table->foreignId('checked_in_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('gate', 40)->nullable();

            // Se incrementa en cada transferencia: invalida el QR anterior
            $table->unsignedSmallInteger('version')->default(1);

            $table->timestamps();

            $table->index(['event_id', 'status']);
            $table->index(['event_id', 'attendee_dni']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
