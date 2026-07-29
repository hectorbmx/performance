<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('training_lifting_set_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('training_assignment_id')
                ->constrained('training_assignments')
                ->cascadeOnDelete();

            $table->foreignId('lifting_row_id')
                ->constrained('training_section_lifting_rows')
                ->cascadeOnDelete();

            $table->unsignedSmallInteger('set_number');
            $table->string('status', 30)->default('completed');
            $table->unsignedSmallInteger('actual_reps')->nullable();
            $table->string('failure_reason', 60)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('logged_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['training_assignment_id', 'lifting_row_id', 'set_number'],
                'tlsl_assignment_row_set_unique'
            );
            $table->index('training_assignment_id', 'tlsl_assignment_index');
            $table->index('lifting_row_id', 'tlsl_lifting_row_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_lifting_set_logs');
    }
};
