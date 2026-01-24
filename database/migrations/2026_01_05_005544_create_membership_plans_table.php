<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('membership_plans', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->text('description')->nullable();

            // Cobro mensual por ahora
            $table->unsignedInteger('billing_cycle_days')->default(30);

            // Activo / Inactivo
            $table->boolean('is_active')->default(true);

            // Límite futuro por número de clientes (null = ilimitado)
            $table->unsignedInteger('client_limit')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_plans');
    }
};
