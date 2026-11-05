<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Productoras (cada una cobra con su propia cuenta de Mercado Pago)
        Schema::create('organizers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('legal_name')->nullable();
            $table->string('cuit', 13)->nullable();
            $table->string('email');
            $table->string('phone', 30)->nullable();
            $table->string('logo_path')->nullable();
            $table->text('description')->nullable();

            // null = usa la comisión general de config/geventos.php
            $table->decimal('commission_percent', 5, 2)->nullable();

            $table->enum('status', ['pending', 'active', 'suspended'])->default('pending');

            // Vinculación OAuth con Mercado Pago (tokens guardados encriptados)
            $table->string('mp_user_id', 30)->nullable();
            $table->text('mp_access_token')->nullable();
            $table->text('mp_refresh_token')->nullable();
            $table->string('mp_public_key')->nullable();
            $table->timestamp('mp_token_expires_at')->nullable();
            $table->timestamp('mp_connected_at')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizers');
    }
};
