<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('coach_subscriptions', function (Blueprint $table) {
            $table->string('billing_status')->default('unpaid')->after('status');
            // unpaid | paid | partial

            $table->date('grace_until')->nullable()->after('billing_status');
            $table->date('paid_at')->nullable()->after('grace_until');

            $table->index(['billing_status', 'grace_until']);
        });
    }

    public function down(): void
    {
        Schema::table('coach_subscriptions', function (Blueprint $table) {
            $table->dropIndex(['billing_status', 'grace_until']);
            $table->dropColumn(['billing_status', 'grace_until', 'paid_at']);
        });
    }
};
