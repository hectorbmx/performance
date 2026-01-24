<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('training_sessions', function (Blueprint $table) {
            $table->id();

            // Relación
            $table->foreignId('coach_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Cabecera
            $table->string('title', 150);
            $table->date('scheduled_at'); // SOLO DATE
            $table->unsignedSmallInteger('duration_minutes')->nullable();

            // Metadata
            $table->enum('level', ['beginner','intermediate','advanced']);
            $table->enum('goal', ['strength','cardio','technique','mobility','mixed']);
            $table->enum('type', ['fitness','functional_fitness','weightlifting','home_training']);

            // Visibilidad
            $table->enum('visibility', ['free','assigned'])->default('assigned');

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['coach_id', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_sessions');
    }
};
