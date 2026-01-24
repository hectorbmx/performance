<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Buscar el nombre real del FK (si existe) para coach_id en coach_training_metrics
        $dbName = DB::getDatabaseName();

        $rows = DB::select("
            SELECT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = ?
              AND TABLE_NAME = 'coach_training_metrics'
              AND COLUMN_NAME = 'coach_id'
              AND REFERENCED_TABLE_NAME IS NOT NULL
            LIMIT 1
        ", [$dbName]);

        if (!empty($rows)) {
            $fkName = $rows[0]->CONSTRAINT_NAME;

            DB::statement("ALTER TABLE coach_training_metrics DROP FOREIGN KEY `$fkName`");
        }

        // 2) Crear la FK correcta: coach_id -> users.id
        Schema::table('coach_training_metrics', function ($table) {
            $table->foreign('coach_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        // Dropear FK actual a users si existe
        $dbName = DB::getDatabaseName();

        $rows = DB::select("
            SELECT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = ?
              AND TABLE_NAME = 'coach_training_metrics'
              AND COLUMN_NAME = 'coach_id'
              AND REFERENCED_TABLE_NAME IS NOT NULL
            LIMIT 1
        ", [$dbName]);

        if (!empty($rows)) {
            $fkName = $rows[0]->CONSTRAINT_NAME;
            DB::statement("ALTER TABLE coach_training_metrics DROP FOREIGN KEY `$fkName`");
        }
    }
};
