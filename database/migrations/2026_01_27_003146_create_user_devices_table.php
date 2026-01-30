<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
       Schema::create('user_devices', function (Blueprint $table) {
    $table->id();

    $table->foreignId('user_id')
        ->constrained('users')
        ->cascadeOnDelete();

    // ios | android (mantener simple para MVP)
    $table->string('platform', 20);

    // Push token del provider (FCM/APNs via FCM)
    $table->text('token');

    // Hash del token para poder hacer UNIQUE/INDEX en MySQL
    $table->char('token_hash', 64)->unique();

    // Toggle por device
    $table->boolean('is_enabled')->default(true);

    // Para política "last device wins"
    $table->timestamp('last_seen_at')->nullable();

    // Opcionales (útiles para UX/debug)
    $table->string('device_name', 100)->nullable();
    $table->string('device_model', 100)->nullable();
    $table->string('app_version', 30)->nullable();

    $table->timestamps();

    $table->index(['user_id', 'is_enabled']);
    $table->index('last_seen_at');
});

    }

    public function down(): void
    {
        Schema::dropIfExists('user_devices');
    }   
};
