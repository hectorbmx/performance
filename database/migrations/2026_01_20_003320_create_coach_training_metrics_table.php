<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coach_training_metrics', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('coach_id');
            $table->unsignedBigInteger('training_metric_id');

            // Si el coach obliga a capturar esta métrica
            $table->boolean('is_required')->default(false);

            // Orden de despliegue en el perfil
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();

            $table->unique(['coach_id', 'training_metric_id']);

            $table->foreign('coach_id')
                ->references('id')
                ->on('coaches')
                ->onDelete('cascade');

            $table->foreign('training_metric_id')
                ->references('id')
                ->on('training_metrics')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coach_training_metrics');
    }
};
