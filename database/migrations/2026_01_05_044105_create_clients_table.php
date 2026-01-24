<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up(): void
{
    Schema::create('clients', function (Blueprint $table) {
        $table->id();

        // Dueño (coach)
        $table->foreignId('coach_id')->constrained('users')->cascadeOnDelete();

        // Datos base
        $table->string('first_name');
        $table->string('last_name')->nullable();
        $table->string('email')->nullable();
        $table->string('phone')->nullable();

        // Control
        $table->boolean('is_active')->default(true);

        $table->timestamps();
        $table->softDeletes();

        // Evitar duplicados por coach (si email existe)
        $table->unique(['coach_id', 'email']);
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
