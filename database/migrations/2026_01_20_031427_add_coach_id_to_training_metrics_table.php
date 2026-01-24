<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
       Schema::table('training_metrics', function (Blueprint $table) {
            $table->unsignedBigInteger('coach_id')->nullable()->after('id');
            $table->index(['coach_id', 'is_active']);

            $table->foreign('coach_id')
                ->references('id')->on('users')
                ->nullOnDelete();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('training_metrics', function (Blueprint $table) {
            //
        });
    }
};
