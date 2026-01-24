<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('client_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coach_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
            $table->foreignId('client_membership_id')->constrained('client_memberships')->onDelete('cascade');
            
            // Montos
            $table->decimal('amount', 10, 2); // Monto original del plan
            $table->decimal('discount', 10, 2)->default(0); // Descuento aplicado
            $table->decimal('final_amount', 10, 2); // Monto final pagado (amount - discount)
            
            // Detalles del pago
            $table->string('payment_method'); // efectivo, transferencia, tarjeta, etc.
            $table->date('payment_date'); // Fecha en que se realizó el pago
            $table->text('notes')->nullable(); // Notas adicionales
            $table->string('status')->default('completed'); // completed, refunded, etc.
            
            $table->timestamps();
            $table->softDeletes();

            // Índices
            $table->index('coach_id');
            $table->index('client_id');
            $table->index('client_membership_id');
            $table->index('payment_date');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_payments');
    }
};