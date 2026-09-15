<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tips', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->after('published_at');
            $table->index(['status', 'expires_at', 'published_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::table('tips', function (Blueprint $table) {
            $table->dropIndex(['status', 'expires_at', 'published_at', 'id']);
            $table->dropColumn('expires_at');
        });
    }
};
