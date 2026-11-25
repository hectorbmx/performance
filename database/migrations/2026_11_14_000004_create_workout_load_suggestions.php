<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('workout_load_suggestions', function (Blueprint $table) {
            $table->id(); $table->uuid('uuid')->unique();
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
            $table->foreignId('coach_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('workout_exercise_id')->constrained('workout_exercises')->restrictOnDelete();
            $table->string('rule_version', 60); $table->boolean('preview')->default(false);
            $table->json('parameters'); $table->json('result'); $table->timestamp('created_at');
        });
    }
    public function down(): void { Schema::dropIfExists('workout_load_suggestions'); }
};
