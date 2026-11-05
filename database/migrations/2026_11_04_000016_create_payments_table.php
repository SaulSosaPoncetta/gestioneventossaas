<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pagos informados por Mercado Pago para cada orden
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('mp_payment_id', 30)->unique();
            $table->string('status', 30);
            $table->string('status_detail', 80)->nullable();
            $table->decimal('amount', 12, 2);
            $table->decimal('net_amount', 12, 2)->nullable();
            $table->decimal('fee_amount', 12, 2)->nullable();
            $table->decimal('marketplace_fee', 12, 2)->nullable();
            $table->string('payment_method', 40)->nullable();
            $table->string('payer_email')->nullable();
            $table->json('raw')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
