<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_assignments', function (Blueprint $table) {
            $table->timestamp('started_at')->nullable()->after('status');
            $table->timestamp('completed_at')->nullable()->after('started_at');

            $table->index(['client_id', 'started_at'], 'ta_client_started_at_idx');
            $table->index(['client_id', 'completed_at'], 'ta_client_completed_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('training_assignments', function (Blueprint $table) {
            $table->dropIndex('ta_client_started_at_idx');
            $table->dropIndex('ta_client_completed_at_idx');
            $table->dropColumn(['started_at', 'completed_at']);
        });
    }
};
