<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('library_videos', function (Blueprint $table) {
            $table->string('source', 20)->default('youtube')->after('training_type_catalog_id');
            $table->string('video_path')->nullable()->after('youtube_id');

            $table->index(['coach_id', 'source']);
        });
    }

    public function down(): void
    {
        Schema::table('library_videos', function (Blueprint $table) {
            $table->dropIndex(['coach_id', 'source']);
            $table->dropColumn(['source', 'video_path']);
        });
    }
};
