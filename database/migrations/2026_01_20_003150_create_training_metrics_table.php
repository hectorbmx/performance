<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_metrics', function (Blueprint $table) {
            $table->id();

            // Identificador estable para usar en código/semillas (ej: back_squat_1rm)
            $table->string('code', 80)->unique();

            // Nombre visible (ej: Back Squat 1RM)
            $table->string('name', 120);

            // Unidad (kg, lb, reps, sec, min, etc.)
            $table->string('unit', 20)->nullable();

            // Tipo de dato/semántica (max, time, volume, distance, etc.)
            $table->string('type', 30)->default('max');

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['is_active', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_metrics');
    }
};
