<?php
// database/migrations/xxxx_xx_xx_create_users_app_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
     Schema::create('users_app', function (Blueprint $table) {
    $table->id();

    $table->foreignId('client_id')
        ->constrained('clients')
        ->cascadeOnDelete();

    $table->string('email')->unique();
    $table->string('password')->nullable();

    $table->boolean('is_active')->default(true);

    $table->string('activation_code', 6)->nullable();
    $table->dateTime('activation_expires_at')->nullable();
    $table->dateTime('activated_at')->nullable();

    $table->dateTime('last_login_at')->nullable();

    $table->timestamps();
    $table->softDeletes();

    $table->unique('client_id');
    $table->index('is_active');
});

    }

    public function down(): void
    {
        Schema::dropIfExists('users_app');
    }
};
