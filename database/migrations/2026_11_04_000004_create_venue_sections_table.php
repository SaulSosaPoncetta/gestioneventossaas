<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sectores del lugar: campo/general (sin butaca) o platea numerada
        Schema::create('venue_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('type', ['general', 'seated'])->default('general');
            $table->unsignedInteger('capacity')->nullable();
            $table->string('color', 7)->nullable();
            $table->json('layout')->nullable(); // posición/forma del sector en el mapa
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_sections');
    }
};
