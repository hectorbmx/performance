<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('metric_catalogs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('coach_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('name', 120);
            // duration | integer | decimal | boolean | text
            $table->string('value_kind', 20);

            // Unidad por defecto: sec, min, km, m, kg, etc.
            $table->string('unit_default', 20)->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['coach_id', 'name']);
            $table->index(['coach_id', 'value_kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metric_catalogs');
    }
};
