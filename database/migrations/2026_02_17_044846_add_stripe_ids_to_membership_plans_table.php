<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_plans', function (Blueprint $table) {
            $table->string('stripe_product_id')->nullable()->after('description')->index();
            $table->string('stripe_price_id')->nullable()->after('stripe_product_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('membership_plans', function (Blueprint $table) {
            $table->dropIndex(['stripe_product_id']);
            $table->dropIndex(['stripe_price_id']);
            $table->dropColumn(['stripe_product_id', 'stripe_price_id']);
        });
    }
};
