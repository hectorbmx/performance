<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_sessions', function (Blueprint $table) {
            $table->foreignId('training_type_catalog_id')
                ->nullable()
                ->after('type') // ajusta si tu campo legacy se llama distinto
                ->constrained('training_type_catalogs')
                ->nullOnDelete(); // si borras un tipo, no rompe sesiones existentes

            $table->index(['coach_id', 'training_type_catalog_id']);
        });
    }

    public function down(): void
    {
        Schema::table('training_sessions', function (Blueprint $table) {
            // primero se elimina la FK, luego el índice, luego la columna
            $table->dropForeign(['training_type_catalog_id']);
            $table->dropIndex(['training_sessions_coach_id_training_type_catalog_id_index']);
            $table->dropColumn('training_type_catalog_id');
        });
    }
};
