<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tips', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->text('body');
            $table->string('type', 16)->default('tip');
            $table->string('category', 32)->default('general');
            $table->string('scope', 16);
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('coach_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('status', 32)->default('draft');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->string('image_disk')->nullable();
            $table->string('image_path')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['scope', 'status', 'published_at', 'id']);
            $table->index(['coach_id', 'status', 'published_at', 'id']);
            $table->index(['author_id', 'status']);
            $table->index(['status', 'created_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tips');
    }
};
