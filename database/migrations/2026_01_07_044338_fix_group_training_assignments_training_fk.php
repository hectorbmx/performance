<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('group_training_assignments', function (Blueprint $table) {
            // Quitar FK actual a trainings
            $table->dropForeign(['training_id']);
        });

        Schema::table('group_training_assignments', function (Blueprint $table) {
            // Renombrar la columna
            $table->renameColumn('training_id', 'training_session_id');
        });

        Schema::table('group_training_assignments', function (Blueprint $table) {
            // Crear FK correcta a training_sessions
            $table->foreign('training_session_id')
                ->references('id')
                ->on('training_sessions')
                ->cascadeOnDelete();

            // Ajustar unique (si MySQL se queja, ver nota abajo)
            $table->dropUnique('grp_trn_day_unique');
            $table->unique(['group_id', 'training_session_id', 'scheduled_for'], 'grp_trn_day_unique');
        });
    }

    public function down(): void
    {
        Schema::table('group_training_assignments', function (Blueprint $table) {
            $table->dropForeign(['training_session_id']);
            $table->dropUnique('grp_trn_day_unique');

            $table->renameColumn('training_session_id', 'training_id');

            $table->foreign('training_id')
                ->references('id')
                ->on('trainings')
                ->cascadeOnDelete();

            $table->unique(['group_id', 'training_id', 'scheduled_for'], 'grp_trn_day_unique');
        });
    }
};
