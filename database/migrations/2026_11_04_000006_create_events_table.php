<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('venue_id')->nullable()->constrained()->nullOnDelete();

            $table->string('title');
            $table->string('slug')->unique();
            $table->string('summary', 300)->nullable();
            $table->longText('description')->nullable();
            $table->string('cover_path')->nullable();
            $table->string('category', 60)->nullable();

            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->dateTime('doors_open_at')->nullable();
            $table->dateTime('sales_start_at')->nullable();
            $table->dateTime('sales_end_at')->nullable();

            $table->enum('status', ['draft', 'published', 'paused', 'cancelled', 'finished'])->default('draft');

            $table->boolean('is_seated')->default(false);      // usa mapa de butacas
            $table->boolean('is_nominative')->default(false);  // cada entrada a nombre y DNI
            $table->boolean('allow_transfers')->default(true); // permite cambiar el titular
            $table->unsignedTinyInteger('max_tickets_per_order')->default(10);

            // null = usa el cargo por servicio general
            $table->decimal('service_fee_percent', 5, 2)->nullable();
            $table->unsignedInteger('capacity')->nullable();

            // Clave propia del evento para validar QR sin conexión en la puerta
            $table->string('checkin_secret', 64)->nullable();

            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
