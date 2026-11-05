<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique(); // referencia pública y external_reference de MP
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->foreignId('organizer_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();

            $table->string('buyer_name');
            $table->string('buyer_email');
            $table->string('buyer_dni', 15)->nullable();
            $table->string('buyer_phone', 30)->nullable();

            $table->enum('status', ['pending', 'paid', 'expired', 'cancelled', 'refunded'])->default('pending');

            $table->unsignedSmallInteger('quantity');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('service_fee', 12, 2)->default(0);  // lo paga el comprador
            $table->decimal('total', 12, 2);
            $table->decimal('platform_fee', 12, 2)->default(0); // marketplace_fee que retiene la plataforma
            $table->string('currency', 3)->default('ARS');

            $table->timestamp('expires_at')->nullable(); // vencimiento de la reserva
            $table->timestamp('paid_at')->nullable();

            $table->string('mp_preference_id')->nullable();
            $table->string('mp_payment_id', 30)->nullable()->index();

            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->index(['status', 'expires_at']);
            $table->index(['event_id', 'status']);
            $table->index('buyer_email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
