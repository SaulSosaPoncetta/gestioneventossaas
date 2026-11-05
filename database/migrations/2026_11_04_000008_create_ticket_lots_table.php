<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Lotes / preventas de cada tipo de entrada, con precio y cupo propios.
        // El stock se controla con: quantity - sold - reserved (con bloqueo de fila).
        Schema::create('ticket_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_type_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('price', 12, 2);
            $table->unsignedInteger('quantity')->nullable(); // null = limitado solo por butacas
            $table->unsignedInteger('sold')->default(0);
            $table->unsignedInteger('reserved')->default(0);
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->enum('status', ['active', 'paused', 'sold_out'])->default('active');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_lots');
    }
};
