<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_memberships', function (Blueprint $table) {
            $table->string('stripe_connected_account_id')->nullable()->after('paid_at')->index();
            $table->string('stripe_checkout_session_id')->nullable()->after('stripe_connected_account_id')->index();
            $table->string('stripe_subscription_id')->nullable()->after('stripe_checkout_session_id')->index();
            $table->string('stripe_status')->nullable()->after('stripe_subscription_id')->index();
            $table->dateTime('stripe_current_period_end')->nullable()->after('stripe_status');
        });
    }

    public function down(): void
    {
        Schema::table('client_memberships', function (Blueprint $table) {
            $table->dropIndex(['stripe_connected_account_id']);
            $table->dropIndex(['stripe_checkout_session_id']);
            $table->dropIndex(['stripe_subscription_id']);
            $table->dropIndex(['stripe_status']);
            $table->dropColumn([
                'stripe_connected_account_id',
                'stripe_checkout_session_id',
                'stripe_subscription_id',
                'stripe_status',
                'stripe_current_period_end',
            ]);
        });
    }
};
