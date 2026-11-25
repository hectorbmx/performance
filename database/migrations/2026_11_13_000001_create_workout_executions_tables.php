<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('workout_executions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('client_execution_id');
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
            $table->foreignId('coach_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('training_session_id')->nullable()->constrained('training_sessions')->nullOnDelete();
            $table->foreignId('training_assignment_id')->nullable()->constrained('training_assignments')->nullOnDelete();
            // Independent from nullable source FKs: retries survive source deletion.
            $table->char('start_fingerprint', 64);
            $table->string('status', 20)->default('in_progress');
            $table->unsignedInteger('revision')->default(1);
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['client_id', 'client_execution_id'], 'we_client_request_unique');
            $table->index(['client_id', 'coach_id', 'id'], 'we_owner_history_index');
        });

        Schema::create('workout_execution_prescriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workout_execution_id')->unique()->constrained('workout_executions')->restrictOnDelete();
            $table->unsignedSmallInteger('schema_version')->default(1);
            $table->json('content');
            $table->char('content_hash', 64);
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workout_execution_prescriptions');
        Schema::dropIfExists('workout_executions');
    }
};
