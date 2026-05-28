<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_apps', function (Blueprint $table) {
            $table->string('stripe_customer_account_id')->nullable()->after('stripe_customer_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('user_apps', function (Blueprint $table) {
            $table->dropIndex(['stripe_customer_account_id']);
            $table->dropColumn('stripe_customer_account_id');
        });
    }
};
