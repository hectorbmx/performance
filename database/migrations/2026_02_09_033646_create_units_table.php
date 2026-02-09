<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();

            // Null = unidad global, con valor = unidad custom del coach
            $table->foreignId('coach_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Identificador técnico (kg, lb, s, min, reps)
            $table->string('code', 20);

            // Nombre legible
            $table->string('name', 100);

            // Símbolo mostrado en UI
            $table->string('symbol', 20);

            // A qué tipo de resultado aplica
            $table->string('result_type', 30);

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // Evitar duplicados por coach
            $table->unique(['coach_id', 'code', 'result_type'], 'units_unique_per_coach');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
