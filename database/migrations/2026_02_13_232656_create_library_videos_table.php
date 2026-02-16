<?php

// database/migrations/xxxx_xx_xx_create_library_videos_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('library_videos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('coach_id')->nullable()
                ->constrained('users') // ajusta si tu coach vive en otra tabla
                ->nullOnDelete();

            $table->foreignId('training_type_catalog_id')->nullable()
                ->constrained('training_type_catalogs')
                ->nullOnDelete();

            $table->string('name', 150);
            $table->text('youtube_url');
            $table->string('youtube_id', 32)->index();
            $table->string('thumbnail_url')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            // Evita duplicar el mismo video dentro del mismo “scope”
            // (global o por coach). Nota: NULL en MySQL permite múltiples NULL,
            // por eso añadimos coach_id en el índice pero no garantiza global único.
            $table->unique(['coach_id', 'youtube_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_videos');
    }
};
