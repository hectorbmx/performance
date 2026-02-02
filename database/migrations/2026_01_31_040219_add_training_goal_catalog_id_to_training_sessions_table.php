<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('training_sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('training_goal_catalog_id')
                ->nullable()
                ->after('goal');

            $table->index('training_goal_catalog_id', 'ts_goal_catalog_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('training_sessions', function (Blueprint $table) {
            $table->dropIndex('ts_goal_catalog_id_idx');
            $table->dropColumn('training_goal_catalog_id');
        });
    }
};
