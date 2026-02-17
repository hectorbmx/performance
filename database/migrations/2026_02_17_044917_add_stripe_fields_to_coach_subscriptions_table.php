<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coach_subscriptions', function (Blueprint $table) {
            // $table->string('stripe_subscription_id')->nullable()->after('external_subscription_id')->index();
            $table->string('stripe_subscription_id')->nullable()->index();

            $table->string('stripe_status')->nullable()->after('billing_status')->index();
            $table->dateTime('stripe_current_period_end')->nullable()->after('ends_at');
        });
    }

    public function down(): void
    {
        Schema::table('coach_subscriptions', function (Blueprint $table) {
            $table->dropIndex(['stripe_subscription_id']);
            $table->dropIndex(['stripe_status']);
            $table->dropColumn(['stripe_subscription_id', 'stripe_status', 'stripe_current_period_end']);
        });
    }
};
