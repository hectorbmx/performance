<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_body_records', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('client_id');

            // Peso medido
            $table->decimal('weight_kg', 6, 2)->nullable();

            // Si luego quieres: cintura_cm, body_fat_pct, etc. los agregamos después
            // $table->decimal('body_fat_pct', 5, 2)->nullable();
            // $table->decimal('waist_cm', 6, 2)->nullable();

            $table->timestamp('recorded_at')->useCurrent();
            $table->string('source', 30)->default('manual');
            $table->string('notes', 255)->nullable();

            $table->timestamps();

            $table->foreign('client_id')
                ->references('id')
                ->on('clients')
                ->onDelete('cascade');

            $table->index(['client_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_body_records');
    }
};
