<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coach_client_plans', function (Blueprint $table) {
            $table->string('payment_provider', 20)
                ->default('manual')
                ->after('currency')
                ->index();
        });

        DB::table('coach_client_plans')
            ->whereNotNull('stripe_price_id')
            ->update(['payment_provider' => 'stripe']);
    }

    public function down(): void
    {
        Schema::table('coach_client_plans', function (Blueprint $table) {
            $table->dropIndex(['payment_provider']);
            $table->dropColumn('payment_provider');
        });
    }
};
