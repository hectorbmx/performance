<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('workout_max_records', function (Blueprint $table) {
            $table->id(); $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
            $table->foreignId('coach_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('workout_exercise_id')->constrained('workout_exercises')->restrictOnDelete();
            $table->string('kind', 20); $table->string('method', 60); $table->string('confidence', 30);
            $table->string('load_mode', 30)->default('total_barbell');
            $table->decimal('value_original', 10, 2); $table->string('unit_original', 2); $table->decimal('value_kg', 14, 6);
            $table->timestamp('measured_at'); $table->string('source', 40); $table->json('evidence')->nullable();
            $table->foreignId('user_app_id')->nullable()->constrained('user_apps')->restrictOnDelete();
            $table->foreignId('recorded_by_coach_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('source_set_id')->nullable()->constrained('workout_execution_sets')->restrictOnDelete();
            $table->unsignedInteger('source_revision')->nullable();
            $table->foreignId('supersedes_id')->nullable()->unique()->constrained('workout_max_records')->restrictOnDelete();
            $table->text('notes')->nullable(); $table->timestamp('created_at');
            $table->unique(['source_set_id', 'source_revision'], 'wmr_set_revision_unique');
            $table->index(['client_id', 'workout_exercise_id', 'measured_at'], 'wmr_client_exercise_date');
        });
    }
    public function down(): void { Schema::dropIfExists('workout_max_records'); }
};
