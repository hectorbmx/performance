<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('workout_execution_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workout_execution_id')->constrained('workout_executions')->restrictOnDelete();
            $table->uuid('set_uuid');
            $table->string('status', 20);
            $table->string('type', 20);
            $table->string('load_mode', 30);
            $table->unsignedSmallInteger('actual_reps')->nullable();
            $table->decimal('actual_load', 10, 2)->nullable();
            $table->string('load_unit', 2)->nullable();
            $table->decimal('load_kg', 14, 6)->nullable();
            $table->decimal('actual_rir', 3, 1)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('performed_at');
            $table->timestamp('received_at');
            $table->unsignedInteger('revision');
            $table->unique(['workout_execution_id', 'set_uuid'], 'wes_execution_set_unique');
        });
        Schema::create('workout_sync_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
            $table->foreignId('user_app_id')->constrained('user_apps')->restrictOnDelete();
            $table->foreignId('workout_execution_id')->constrained('workout_executions')->restrictOnDelete();
            $table->uuid('operation_id');
            $table->char('payload_hash', 64);
            $table->json('response');
            $table->timestamp('created_at');
            $table->unique(['client_id', 'operation_id'], 'wso_client_operation_unique');
        });
        Schema::create('workout_set_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workout_execution_set_id')->constrained('workout_execution_sets')->restrictOnDelete();
            $table->foreignId('workout_sync_operation_id')->unique()->constrained('workout_sync_operations')->restrictOnDelete();
            $table->unsignedInteger('revision');
            $table->json('before')->nullable();
            $table->json('after');
            $table->timestamp('created_at');
            $table->unique(['workout_execution_set_id', 'revision'], 'wsr_set_revision_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workout_set_revisions');
        Schema::dropIfExists('workout_sync_operations');
        Schema::dropIfExists('workout_execution_sets');
    }
};
