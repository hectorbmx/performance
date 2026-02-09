<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_section_results', function (Blueprint $table) {
            // Garantiza 1 resultado por (assignment, section)
            $table->unique(
                ['training_assignment_id', 'training_section_id'],
                'tsr_unique_assignment_section'
            );
        });
    }

    public function down(): void
    {
        Schema::table('training_section_results', function (Blueprint $table) {
            $table->dropUnique('tsr_unique_assignment_section');
        });
    }
};
