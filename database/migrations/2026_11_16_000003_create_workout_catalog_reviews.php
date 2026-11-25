<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::create('workout_catalog_reviews',function(Blueprint $t){
        $t->id();$t->foreignId('workout_exercise_id')->constrained('workout_exercises')->restrictOnDelete();
        $t->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
        $t->string('action',20);$t->text('reason');$t->json('before');$t->json('after');$t->timestamp('created_at');
    }); }
    public function down(): void {throw new RuntimeException('Conservar auditoría de catálogo; corregir hacia adelante.');}
};
