<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Estado de cada butaca para un evento puntual (se genera al publicar el evento)
        Schema::create('event_seats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seat_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_type_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['available', 'held', 'sold', 'blocked'])->default('available');
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('held_until')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'seat_id']);
            $table->index(['event_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_seats');
    }
};
