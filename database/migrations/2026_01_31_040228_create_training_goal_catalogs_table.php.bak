<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('training_goal_catalogs', function (Blueprint $table) {
            $table->id();

            // “key” estable para código (strength, cardio, etc.)
            $table->string('key', 50)->unique();

            // Nombre para UI (Fuerza, Cardio, Técnica...)
            $table->string('name', 100);

            $table->string('description', 255)->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_goal_catalogs');
    }
};
