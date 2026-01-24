<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('training_goal_catalogs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('coach_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['coach_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_goal_catalogs');
    }
};
