<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach (['workout_executions','workout_max_records','workout_load_suggestions','workout_exercise_goals'] as $name) {
            Schema::table($name, function (Blueprint $t) {
                $t->foreignId('owner_user_app_id')->nullable()->constrained('user_apps')->restrictOnDelete();
                $t->string('data_scope',20)->default('legacy_coach')->index();
            });
            // Keep existing FK rules and indexes; only relax columns for personal rows.
            Schema::table($name,function(Blueprint $t) {
                $t->unsignedBigInteger('client_id')->nullable()->change();
                $t->unsignedBigInteger('coach_id')->nullable()->change();
            });
        }
        Schema::table('workout_executions',fn(Blueprint $t)=>$t->unique(['owner_user_app_id','client_execution_id'],'we_personal_request_unique'));
        Schema::table('workout_exercise_goals',fn(Blueprint $t)=>$t->unique(['owner_user_app_id','request_id'],'weg_personal_request_unique'));
        Schema::table('workout_sync_operations',function(Blueprint $t) {
            $t->unsignedBigInteger('client_id')->nullable()->change();
            $t->unique(['user_app_id','operation_id'],'wso_actor_operation_unique');
        });
        Schema::create('athlete_gym_preferences',function(Blueprint $t) {
            $t->id();$t->foreignId('user_app_id')->unique()->constrained('user_apps')->restrictOnDelete();
            $t->json('blocked_exercise_codes')->nullable();$t->json('blocked_demands')->nullable();$t->timestamps();
        });
        Schema::create('workout_ownership_claims',function(Blueprint $t) {
            $t->id();$t->foreignId('user_app_id')->constrained('user_apps')->restrictOnDelete();
            $t->string('resource_table',40);$t->unsignedBigInteger('resource_id');
            $t->unsignedBigInteger('legacy_client_id')->nullable();$t->unsignedBigInteger('legacy_coach_id')->nullable();
            $t->string('evidence',80);$t->timestamp('created_at');
            $t->unique(['resource_table','resource_id'],'woc_resource_unique');
        });
        if (DB::getDriverName()==='mysql') {
            foreach (['workout_executions','workout_max_records','workout_load_suggestions','workout_exercise_goals'] as $name) {
                DB::statement("ALTER TABLE `$name` ADD CONSTRAINT `{$name}_ownership_check` CHECK ((data_scope = 'personal_gym' AND owner_user_app_id IS NOT NULL) OR (data_scope IN ('coach_assigned','legacy_coach') AND owner_user_app_id IS NULL AND client_id IS NOT NULL AND coach_id IS NOT NULL))");
            }
        }
    }
    public function down(): void {throw new RuntimeException('Conservar datos GYM: corregir hacia adelante sin rollback destructivo.');}
};
