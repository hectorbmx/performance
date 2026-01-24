<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('training_assignments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('training_session_id')
                ->constrained('training_sessions')
                ->cascadeOnDelete();

            // Asignación a cliente (atleta)
            $table->foreignId('client_id')
                ->constrained('clients')
                ->cascadeOnDelete();

            // Estado de la asignación (MVP)
            $table->enum('status', ['scheduled','in_progress','completed','skipped','cancelled'])
                ->default('scheduled');

            $table->timestamps();

            $table->unique(['training_session_id','client_id']);
            $table->index(['client_id','status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_assignments');
    }
};
