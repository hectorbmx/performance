<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('training_section_exercise_blocks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('training_section_id')
                ->constrained('training_sections')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('exercise_catalog_id')->nullable();
            $table->string('exercise_name', 160);
            $table->text('notes')->nullable();
            $table->unsignedSmallInteger('order')->default(1);
            $table->timestamps();

            $table->index(['training_section_id', 'order'], 'tseb_section_order_index');
            $table->index('exercise_catalog_id', 'tseb_exercise_catalog_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_section_exercise_blocks');
    }
};
