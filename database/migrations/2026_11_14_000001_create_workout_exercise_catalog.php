<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('workout_taxonomy_terms', function (Blueprint $table) {
            $table->id(); $table->string('kind', 20); $table->string('code', 80); $table->json('names');
            $table->unique(['kind', 'code'], 'wtt_kind_code_unique');
        });
        Schema::create('workout_exercises', function (Blueprint $table) {
            $table->id(); $table->string('code', 120)->unique();
            $table->foreignId('coach_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('variant_of_id')->nullable()->constrained('workout_exercises')->nullOnDelete();
            $table->json('names'); $table->string('load_mode', 30); $table->string('fatigue', 20);
            $table->boolean('unilateral')->default(false); $table->boolean('is_active')->default(true);
            $table->string('review_status', 20)->default('pending'); $table->string('seed_version', 40)->nullable();
            $table->text('notes')->nullable(); $table->timestamps();
            $table->index(['coach_id', 'is_active'], 'we_catalog_visibility');
        });
        Schema::create('workout_exercise_terms', function (Blueprint $table) {
            $table->foreignId('workout_exercise_id')->constrained('workout_exercises')->restrictOnDelete();
            $table->foreignId('workout_taxonomy_term_id')->constrained('workout_taxonomy_terms')->restrictOnDelete();
            $table->string('role', 20)->default('classification');
            $table->primary(['workout_exercise_id', 'workout_taxonomy_term_id'], 'wet_primary');
        });
        // The old exercise_catalog_id had no backing catalogue. Never reinterpret
        // arbitrary legacy integers as IDs of this new catalogue.
        Schema::table('training_section_exercise_blocks', function (Blueprint $table) {
            $table->foreignId('workout_exercise_id')->nullable()->constrained('workout_exercises')->nullOnDelete();
        });
    }
    public function down(): void
    {
        Schema::table('training_section_exercise_blocks', function (Blueprint $table) {
            if (Schema::getConnection()->getDriverName() === 'sqlite') $table->dropColumn('workout_exercise_id');
            else $table->dropConstrainedForeignId('workout_exercise_id');
        });
        Schema::dropIfExists('workout_exercise_terms'); Schema::dropIfExists('workout_exercises'); Schema::dropIfExists('workout_taxonomy_terms');
    }
};
