<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_notifications', function (Blueprint $table) {
            $table->id();

            // nullable por si a futuro mandas a "grupo/topic" o batch sin usuario específico
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // nullable porque puede haber notificación "por usuario" y luego expandirse a devices
            $table->foreignId('device_id')
                ->nullable()
                ->constrained('user_devices')
                ->nullOnDelete();

            // ej: training_assigned
            $table->string('type', 50);

            $table->string('title', 150);
            $table->string('body', 255);

            // payload para deep link / contexto (training_id, scheduled_for, etc.)
            $table->json('data')->nullable();

            // queued | sent | failed
            $table->string('status', 20)->default('queued');

            // fcm | apns (si un día separas)
            $table->string('provider', 20)->nullable();

            // id que regrese el provider, si aplica
            $table->string('provider_message_id', 120)->nullable();

            $table->text('error')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'type', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_notifications');
    }
};
