<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('workout_athlete_settings', function (Blueprint $table) {
            $table->id(); $table->foreignId('client_id')->unique()->constrained('clients')->restrictOnDelete();
            $table->foreignId('coach_id')->constrained('users')->restrictOnDelete();
            $table->boolean('generator_enabled')->default(false);
            $table->json('blocked_exercise_codes'); $table->json('blocked_demands');
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('workout_athlete_settings'); }
};
