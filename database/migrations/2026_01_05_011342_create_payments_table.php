<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            // Relación con la suscripción
            $table->foreignId('coach_subscription_id')
                ->constrained('coach_subscriptions')
                ->cascadeOnDelete();

            // Redundante útil para reportes
            $table->foreignId('coach_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Monto pagado (snapshot real)
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('MXN');

            // Datos del pago
            $table->date('paid_at');
            $table->string('method')->default('manual'); // manual | stripe | mp (futuro)
            $table->string('reference')->nullable();

            // Comprobante (local ahora, S3 futuro)
            $table->string('receipt_disk')->default('public');
            $table->string('receipt_path')->nullable();

            // Auditoría (admin)
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['coach_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
