<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Equipo de cada productora: dueño, administradores, staff y operadores de puerta
        Schema::create('organizer_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['owner', 'admin', 'staff', 'door'])->default('staff');
            $table->timestamps();

            $table->unique(['organizer_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizer_user');
    }
};
