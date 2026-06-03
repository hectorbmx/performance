<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->dropForeignIfExists('user_devices', ['user_id']);
        $this->dropForeignIfExists('push_notifications', ['user_id']);

        Schema::table('user_devices', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('user_apps')->cascadeOnDelete();
        });

        Schema::table('push_notifications', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('user_apps')->nullOnDelete();

            if (!Schema::hasColumn('push_notifications', 'read_at')) {
                $table->timestamp('read_at')->nullable()->after('error');
            }
        });
    }

    public function down(): void
    {
        $this->dropForeignIfExists('user_devices', ['user_id']);
        $this->dropForeignIfExists('push_notifications', ['user_id']);

        Schema::table('push_notifications', function (Blueprint $table) {
            if (Schema::hasColumn('push_notifications', 'read_at')) {
                $table->dropColumn('read_at');
            }
        });

        Schema::table('user_devices', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('push_notifications', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    private function dropForeignIfExists(string $tableName, array $columns): void
    {
        try {
            Schema::table($tableName, function (Blueprint $table) use ($columns) {
                $table->dropForeign($columns);
            });
        } catch (Throwable) {
            //
        }
    }
};
