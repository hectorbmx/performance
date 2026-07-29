<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('training_section_lifting_rows', function (Blueprint $table) {
            $table->id();

            $table->foreignId('exercise_block_id')
                ->constrained('training_section_exercise_blocks')
                ->cascadeOnDelete();

            $table->decimal('percentage', 5, 2)->nullable();
            $table->unsignedSmallInteger('reps');
            $table->unsignedSmallInteger('sets');
            $table->unsignedInteger('rest_seconds')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedSmallInteger('order')->default(1);
            $table->timestamps();

            $table->index(['exercise_block_id', 'order'], 'tslr_block_order_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_section_lifting_rows');
    }
};
