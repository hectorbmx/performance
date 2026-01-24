<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {

    public function up(): void
    {
        // 1) Desactivar FK checks (MySQL)
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        // 2) Quitar UNIQUE viejo
        Schema::table('training_assignments', function (Blueprint $table) {
            $table->dropUnique('training_assignments_training_session_id_client_id_unique');
        });

        // 3) Crear UNIQUE nuevo con fecha
        Schema::table('training_assignments', function (Blueprint $table) {
            $table->unique(
                ['training_session_id', 'client_id', 'scheduled_for'],
                'ta_unique_session_client_date'
            );
        });

        // 4) Reactivar FK checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        Schema::table('training_assignments', function (Blueprint $table) {
            $table->dropUnique('ta_unique_session_client_date');
        });

        Schema::table('training_assignments', function (Blueprint $table) {
            $table->unique(
                ['training_session_id', 'client_id'],
                'training_assignments_training_session_id_client_id_unique'
            );
        });

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
