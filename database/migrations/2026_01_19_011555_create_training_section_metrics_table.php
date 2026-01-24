<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('training_section_metrics', function (Blueprint $table) {
            $table->id();

            $table->foreignId('coach_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('training_section_id')
                ->constrained('training_sections')
                ->cascadeOnDelete();

            $table->foreignId('metric_id')
                ->constrained('metric_catalogs')
                ->cascadeOnDelete();

            // Etiqueta visible en UI (opcional)
            $table->string('label', 120)->nullable();

            // ¿Es obligatorio capturar este resultado?
            $table->boolean('required')->default(true);

            // single | repeatable
            $table->string('input_mode', 20);

            // Intervalo / Set / Bloque (solo si repeatable)
            $table->string('repeat_label', 50)->nullable();

            // Permite sobreescribir unidad
            $table->string('unit_override', 20)->nullable();

            $table->smallInteger('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->unique([
                'training_section_id',
                'metric_id'
            ], 'tsm_section_metric_unique');

            $table->index(['coach_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_section_metrics');
    }
};
