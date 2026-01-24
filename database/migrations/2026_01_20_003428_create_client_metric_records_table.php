<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_metric_records', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('training_metric_id');

            // Valor medido (ej: 120.5 kg, 5 reps, 12.34 min, etc.)
            $table->decimal('value', 10, 2);

            // Fecha/hora de la medición (si no la mandan, se puede usar now() en backend)
            $table->timestamp('recorded_at')->useCurrent();

            // De dónde salió el dato (manual, test, training_session, etc.)
            $table->string('source', 30)->default('manual');

            // Nota opcional (ej: "con belt", "PR", "sin fallo")
            $table->string('notes', 255)->nullable();

            $table->timestamps();

            $table->foreign('client_id')
                ->references('id')
                ->on('clients')
                ->onDelete('cascade');

            $table->foreign('training_metric_id')
                ->references('id')
                ->on('training_metrics')
                ->onDelete('cascade');

            // Para "último valor por métrica" y listados por fecha
            $table->index(['client_id', 'training_metric_id', 'recorded_at']);
            $table->index(['training_metric_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_metric_records');
    }
};
