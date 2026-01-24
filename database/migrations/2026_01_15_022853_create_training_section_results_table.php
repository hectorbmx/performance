<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('training_section_results', function (Blueprint $table) {
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

            $table->enum('result_type', ['number', 'time', 'text', 'bool', 'json']);

            $table->decimal('value_number', 10, 2)->nullable();
            $table->unsignedInteger('value_time_seconds')->nullable();
            $table->text('value_text')->nullable();
            $table->boolean('value_bool')->nullable();
            $table->json('value_json')->nullable();

            $table->string('unit', 20)->nullable();
            $table->text('notes')->nullable();

            $table->timestamp('recorded_at')->nullable();

            $table->timestamps();

            $table->index(['training_assignment_id', 'training_section_id'], 'tsr_assignment_section_idx');
            $table->index(['client_id', 'created_at'], 'tsr_client_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_section_results');
    }
};
