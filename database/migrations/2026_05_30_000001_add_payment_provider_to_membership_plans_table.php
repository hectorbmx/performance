<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_plans', function (Blueprint $table) {
            $table->string('payment_provider', 20)
                ->default('stripe')
                ->after('currency')
                ->index();
        });

        DB::table('membership_plans')
            ->whereNull('stripe_price_id')
            ->update(['payment_provider' => 'manual']);
    }

    public function down(): void
    {
        Schema::table('membership_plans', function (Blueprint $table) {
            $table->dropIndex(['payment_provider']);
            $table->dropColumn('payment_provider');
        });
    }
};
