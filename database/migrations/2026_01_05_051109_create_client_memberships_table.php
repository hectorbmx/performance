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
        Schema::create('client_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coach_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
            $table->foreignId('coach_client_plan_id')->constrained('coach_client_plans')->onDelete('cascade');
            
            // Snapshots del plan
            $table->string('plan_name_snapshot');
            $table->decimal('price_snapshot', 10, 2);
            $table->integer('billing_cycle_days_snapshot');
            
            // Fechas
            $table->date('starts_at');
            $table->date('ends_at');
            $table->date('next_renewal_at')->nullable();
            $table->smallInteger('reminder_days_before')->nullable();
            
            // Estados
            $table->string('status')->default('active'); // active, cancelled, expired
            $table->string('billing_status')->default('unpaid'); // paid, unpaid
            $table->date('grace_until')->nullable();
            $table->date('paid_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes();

            // Índices
            $table->index('coach_id');
            $table->index('client_id');
            $table->index('coach_client_plan_id');
            $table->index('status');
            $table->index('billing_status');
            $table->index('grace_until');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_memberships');
    }
};