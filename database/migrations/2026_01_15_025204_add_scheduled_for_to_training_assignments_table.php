<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('training_assignments', function (Blueprint $table) {
            // Fecha efectiva de la asignación (personal/grupal/libre materializado)
            $table->date('scheduled_for')->nullable()->after('client_id');

            // Índice para búsquedas por cliente + fecha (feed)
            $table->index(['client_id', 'scheduled_for'], 'ta_client_scheduled_for_idx');
        });
    }

    public function down(): void
    {
        Schema::table('training_assignments', function (Blueprint $table) {
            $table->dropIndex('ta_client_scheduled_for_idx');
            $table->dropColumn('scheduled_for');
        });
    }
};
