<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('training_sections', function (Blueprint $table) {
            $table->id();

            $table->foreignId('training_session_id')
                ->constrained('training_sessions')
                ->cascadeOnDelete();

            // Orden y contenido
            $table->unsignedSmallInteger('order');
            $table->string('name', 100);
            $table->longText('description')->nullable(); // Quill

            // Resultados
            $table->boolean('accepts_results')->default(false);
            $table->string('result_type', 30)->nullable(); // kg, lb, time, distance, reps, etc.

            $table->timestamps();

            $table->unique(['training_session_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_sections');
    }
};
