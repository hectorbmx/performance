<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_daily_health_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->date('metric_date');
            $table->unsignedInteger('steps')->default(0);
            $table->unsignedInteger('calories')->default(0);
            $table->unsignedInteger('active_minutes')->default(0);
            $table->string('source')->default('device');
            $table->timestamps();

            $table->unique(['client_id', 'metric_date']);
            $table->index(['client_id', 'metric_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_daily_health_metrics');
    }
};
