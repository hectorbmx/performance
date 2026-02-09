<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_section_completions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('training_assignment_id')
                ->constrained('training_assignments')
                ->cascadeOnDelete();

            $table->foreignId('training_section_id')
                ->constrained('training_sections')
                ->cascadeOnDelete();

            $table->foreignId('client_id')
                ->constrained('clients')
                ->cascadeOnDelete();

            $table->timestamp('completed_at')->useCurrent();

            $table->timestamps();

            // 1 completion por (assignment, section)
            $table->unique(
                ['training_assignment_id', 'training_section_id'],
                'tsc_unique_assignment_section'
            );

            // índices útiles para queries comunes
            $table->index(['client_id', 'completed_at'], 'tsc_client_completed_idx');
            $table->index(['training_assignment_id', 'completed_at'], 'tsc_assignment_completed_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_section_completions');
    }
};
