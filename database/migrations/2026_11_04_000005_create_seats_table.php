<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Butacas físicas de un sector numerado (plantilla del lugar)
        Schema::create('seats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_section_id')->constrained()->cascadeOnDelete();
            $table->string('row_label', 10);
            $table->string('number', 10);
            $table->string('label', 30)->nullable();
            $table->decimal('x', 8, 2)->nullable();
            $table->decimal('y', 8, 2)->nullable();
            $table->enum('kind', ['standard', 'accessible', 'companion'])->default('standard');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['venue_section_id', 'row_label', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seats');
    }
};
