<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {

    public function up(): void
    {
        // Crear índices normales para que las FK no dependan del unique viejo
        Schema::table('training_assignments', function (Blueprint $table) {
            $table->index('training_session_id', 'ta_training_session_id_index');
            $table->index('client_id', 'ta_client_id_index');
        });

        // Ahora sí quitar UNIQUE viejo
        Schema::table('training_assignments', function (Blueprint $table) {
            $table->dropUnique('training_assignments_training_session_id_client_id_unique');
        });

        // Crear UNIQUE nuevo con fecha
        Schema::table('training_assignments', function (Blueprint $table) {
            $table->unique(
                ['training_session_id', 'client_id', 'scheduled_for'],
                'ta_unique_session_client_date'
            );
        });
    }

    public function down(): void
    {
        Schema::table('training_assignments', function (Blueprint $table) {
            $table->dropUnique('ta_unique_session_client_date');
        });

        Schema::table('training_assignments', function (Blueprint $table) {
            $table->unique(
                ['training_session_id', 'client_id'],
                'training_assignments_training_session_id_client_id_unique'
            );
        });

        Schema::table('training_assignments', function (Blueprint $table) {
            $table->dropIndex('ta_training_session_id_index');
            $table->dropIndex('ta_client_id_index');
        });
    }
};