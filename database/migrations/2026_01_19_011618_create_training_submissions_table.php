<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('training_submissions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('coach_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('training_session_id')
                ->constrained('training_sessions')
                ->cascadeOnDelete();

            $table->foreignId('client_id')
                ->constrained('clients')
                ->cascadeOnDelete();

            $table->timestamp('submitted_at')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // 1 submission por cliente y entrenamiento
            $table->unique(
                ['training_session_id', 'client_id'],
                'ts_client_unique'
            );

            $table->index(['coach_id', 'training_session_id']);
            $table->index(['coach_id', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_submissions');
    }
};
