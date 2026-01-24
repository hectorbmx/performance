<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('coach_subscriptions', function (Blueprint $table) {
            $table->id();

            // Coach (tenant owner)
            $table->foreignId('coach_id')->constrained('users')->cascadeOnDelete();

            // Plan actual (referencia)
            $table->foreignId('membership_plan_id')->constrained('membership_plans')->restrictOnDelete();

            // Snapshot del plan al momento de asignar / renovar
            $table->string('plan_name_snapshot');
            $table->unsignedInteger('billing_cycle_days_snapshot')->default(30);
            $table->unsignedInteger('client_limit_snapshot')->nullable(); // null = ilimitado

            // Vigencia
            $table->date('starts_at');
            $table->date('ends_at');

            // Recordatorios
            $table->date('next_renewal_at')->nullable();
            $table->unsignedSmallInteger('reminder_days_before')->default(5);

            // Estado
            $table->string('status')->default('active'); 
            // active | past_due | suspended | cancelled

            $table->timestamps();
            $table->softDeletes();

            $table->index(['coach_id', 'status']);
            $table->index(['ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coach_subscriptions');
    }
};
