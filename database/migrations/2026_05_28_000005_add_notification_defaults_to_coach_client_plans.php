<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coach_client_plans', function (Blueprint $table) {
            $table->unsignedSmallInteger('reminder_days_before')->default(5)->after('billing_cycle_days');
            $table->unsignedSmallInteger('grace_days')->default(0)->after('reminder_days_before');
        });
    }

    public function down(): void
    {
        Schema::table('coach_client_plans', function (Blueprint $table) {
            $table->dropColumn(['reminder_days_before', 'grace_days']);
        });
    }
};
