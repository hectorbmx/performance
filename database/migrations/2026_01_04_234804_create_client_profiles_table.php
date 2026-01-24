<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('client_profiles', function (Blueprint $table) {
            $table->id();

            // Relación con users (cliente)
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Tenant: coach dueño del cliente
            $table->foreignId('coach_id')->constrained('users')->cascadeOnDelete();

            $table->string('display_name');
            $table->string('phone')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->unique('user_id');
            $table->index('coach_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_profiles');
    }
};
