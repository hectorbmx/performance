<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('training_type_catalogs', function (Blueprint $table) {
            $table->string('behavior', 40)
                ->default('standard')
                ->after('description')
                ->index();
        });

        DB::table('training_type_catalogs')
            ->whereRaw('LOWER(name) IN (?, ?, ?)', ['lifting', 'weightlifting', 'levantamiento'])
            ->update(['behavior' => 'lifting']);
    }

    public function down(): void
    {
        Schema::table('training_type_catalogs', function (Blueprint $table) {
            $table->dropIndex(['behavior']);
            $table->dropColumn('behavior');
        });
    }
};
