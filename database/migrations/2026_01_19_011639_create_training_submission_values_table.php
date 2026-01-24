<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('training_submission_values', function (Blueprint $table) {
            $table->id();

            $table->foreignId('coach_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('training_submission_id')
                ->constrained('training_submissions')
                ->cascadeOnDelete();

            $table->foreignId('training_section_metric_id')
                ->constrained('training_section_metrics')
                ->cascadeOnDelete();

            /**
             * item_index:
             * 0   => single
             * 1..N => repeatable (intervalos / sets)
             */
            $table->unsignedSmallInteger('item_index')->default(0);

            // Valores tipados (uno solo se usa según value_kind)
            $table->unsignedInteger('value_duration_seconds')->nullable();
            $table->integer('value_int')->nullable();
            $table->decimal('value_decimal', 10, 3)->nullable();
            $table->text('value_text')->nullable();
            $table->boolean('value_bool')->nullable();

            $table->timestamps();

            $table->unique(
                ['training_submission_id', 'training_section_metric_id', 'item_index'],
                'tsv_unique'
            );

            $table->index(['coach_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_submission_values');
    }
};
