<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('coach_profiles', function (Blueprint $table) {
            $table->id();

            // Relación con users (coach)
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Datos básicos
            $table->string('display_name');
            $table->string('phone')->nullable();

            // Estado del coach (SaaS)
            $table->string('status')->default('active'); 
            // active | inactive | suspended | trial | cancelled

            $table->timestamp('suspended_at')->nullable();
            $table->string('suspension_reason')->nullable();

            // Auditoría (admin)
            $table->foreignId('created_by')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->foreignId('updated_by')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->softDeletes();
            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coach_profiles');
    }
};
