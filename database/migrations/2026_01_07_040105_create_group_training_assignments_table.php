<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('group_training_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->foreignId('training_id')->constrained('trainings')->cascadeOnDelete();
            $table->date('scheduled_for'); // día asignado
            $table->text('notes')->nullable();
            $table->timestamps();

            // Evitar duplicar el mismo entrenamiento para el mismo grupo en el mismo día
            $table->unique(['group_id', 'training_id', 'scheduled_for'], 'grp_trn_day_unique');

            $table->index(['group_id', 'scheduled_for']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_training_assignments');
    }
};