<?php

// database/migrations/xxxx_xx_xx_create_training_section_library_videos_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('training_section_library_videos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('training_section_id')
                ->constrained('training_sections')
                ->cascadeOnDelete();

            $table->foreignId('library_video_id')
                ->constrained('library_videos')
                ->cascadeOnDelete();

            $table->unsignedInteger('order')->default(1);
            $table->string('notes', 255)->nullable();

            $table->timestamps();

            $table->unique(['training_section_id', 'library_video_id'], 'tslv_unique');
            $table->index(['training_section_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_section_library_videos');
    }
};
