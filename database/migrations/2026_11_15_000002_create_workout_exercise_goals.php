<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::create('workout_exercise_goals', function (Blueprint $table) {
        $table->id(); $table->uuid('request_id'); $table->char('fingerprint',64);
        $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
        $table->foreignId('coach_id')->constrained('users')->restrictOnDelete();
        $table->foreignId('workout_exercise_id')->constrained('workout_exercises')->restrictOnDelete();
        $table->foreignId('user_app_id')->nullable()->constrained('user_apps')->restrictOnDelete();
        $table->foreignId('recorded_by_coach_id')->nullable()->constrained('users')->restrictOnDelete();
        $table->date('scheduled_for'); $table->decimal('load_original',7,2); $table->string('unit',2);
        $table->decimal('load_kg',12,6); $table->unsignedSmallInteger('reps'); $table->decimal('rir',3,1);
        $table->text('notes')->nullable(); $table->timestamp('created_at');
        $table->unique(['client_id','request_id'],'weg_client_request_unique');
        $table->index(['client_id','workout_exercise_id','scheduled_for'],'weg_client_exercise_date');
    }); }
    public function down(): void { Schema::dropIfExists('workout_exercise_goals'); }
};
