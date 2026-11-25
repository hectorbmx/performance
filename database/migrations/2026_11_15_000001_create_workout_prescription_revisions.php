<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up():void { Schema::create('workout_prescription_revisions',function(Blueprint $table){
        $table->id();$table->foreignId('workout_execution_id')->constrained('workout_executions')->restrictOnDelete();
        $table->foreignId('workout_sync_operation_id')->unique()->constrained('workout_sync_operations')->restrictOnDelete();
        $table->unsignedInteger('execution_revision');$table->unsignedSmallInteger('schema_version')->default(2);
        $table->json('content');$table->char('content_hash',64);$table->string('reason',60);$table->timestamp('created_at');
        $table->unique(['workout_execution_id','execution_revision'],'wpr_execution_revision_unique');
    }); }
    public function down():void {Schema::dropIfExists('workout_prescription_revisions');}
};
