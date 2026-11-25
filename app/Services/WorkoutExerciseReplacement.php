<?php
namespace App\Services;

use App\Models\Client;
use App\Models\UserApp;
use App\Models\WorkoutExecution;
use App\Models\WorkoutPrescriptionRevision;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkoutExerciseReplacement {
    public function replace(Request $request,UserApp $actor):array {
        $data=$request->validate([
            'operation_id'=>['required','uuid'],'expected_execution_revision'=>['required','integer','min:1'],
            'target_exercise_uuid'=>['required','uuid'],'replacement'=>['required','array'],
            'replacement.exercise_id'=>['required','integer','min:1'],'replacement.sets'=>['required','integer','min:1','max:6'],
            'replacement.reps_min'=>['required','integer','min:1','max:30'],'replacement.reps_max'=>['required','integer','min:1','max:30'],
            'replacement.target_rir'=>['required','numeric','min:0','max:5','regex:/^\d(?:\.\d)?$/'],
            'replacement.rest_seconds'=>['required','integer','min:15','max:600'],
            'replacement.chosen_load'=>['present','nullable','numeric','min:0','max:10000','regex:/^\d{1,5}(?:\.\d{1,2})?$/'],
            'replacement.unit'=>['required','in:kg,lb'],'replacement.suggestion_id'=>['nullable','uuid'],'replacement.notes'=>['nullable','string','max:2000'],
        ]);
        abort_if($data['replacement']['reps_min']>$data['replacement']['reps_max'],422,'Rango inválido.');
        $uuid=$request->route('execution');$op=strtolower($data['operation_id']);
        $fingerprint=$data;unset($fingerprint['operation_id']);
        $hash=hash('sha256','replace-exercise:'.$uuid.':'.json_encode($fingerprint,JSON_THROW_ON_ERROR));
        return DB::transaction(function()use($request,$actor,$data,$uuid,$op,$hash){
            $ownership=app(WorkoutOwnership::class);$ownership->lock($actor);$client=$actor;
            $execution=$ownership->executions($actor)->where('uuid',$uuid)->lockForUpdate()->firstOrFail();
            $previous=DB::table('workout_sync_operations')->where('user_app_id',$actor->id)->where('operation_id',$op)->lockForUpdate()->first();
            if($previous){abort_unless(hash_equals($previous->payload_hash,$hash),409,'La operación ya se usó con otro contenido.');return json_decode($previous->response,true,512,JSON_THROW_ON_ERROR);}
            abort_unless($execution->data_scope===WorkoutOwnership::PERSONAL,422,'Esta sesión requiere resolver su propiedad antes de sustituir ejercicios.');
            abort_unless($execution->status==='in_progress',422,'La sesión está finalizada.');
            abort_unless($execution->revision===(int)$data['expected_execution_revision'],409,'La sesión cambió. Actualiza antes de sustituir.');
            $content=$execution->current_prescription->content;
            abort_unless(($content['source']??null)==='athlete_generated',422,'La sustitución guiada se aplica a sesiones creadas con el constructor.');
            $selected=[];$found=false;
            foreach($content['sections'] as &$section)foreach($section['exercises'] as &$exercise){
                if($exercise['uuid']===strtolower($data['target_exercise_uuid'])){
                    $setIds=array_column($exercise['sets'],'uuid');
                    abort_if(DB::table('workout_execution_sets')->where('workout_execution_id',$execution->id)->whereIn('set_uuid',$setIds)->exists(),422,'Conserva el ejercicio que ya tiene registros; solo se sustituye uno sin comenzar.');
                    abort_if((int)$exercise['workout_exercise_id']===(int)$data['replacement']['exercise_id'],422,'Elige una alternativa distinta.');
                    $selected[]=(int)$data['replacement']['exercise_id'];$found=true;
                }else $selected[]=(int)$exercise['workout_exercise_id'];
            }
            unset($section,$exercise);abort_unless($found,404);
            abort_unless(count($selected)===count(array_unique($selected)),422,'El ejercicio ya está en esta sesión.');
            $validation=clone $request;$validation->merge(['selected_ids'=>$selected,'equipment'=>$content['equipment']??[],'discover_equipment'=>false]);
            app(WorkoutRoutineRecommendations::class)->recommend($validation,$client);
            $replacement=app(WorkoutGeneratedExecution::class)->buildExercise($client,$data['replacement']);
            foreach($content['sections'] as &$section)foreach($section['exercises'] as &$exercise)if($exercise['uuid']===strtolower($data['target_exercise_uuid']))$exercise=$replacement;
            unset($section,$exercise);
            $execution->increment('revision');
            $operationId=DB::table('workout_sync_operations')->insertGetId(['client_id'=>$execution->client_id,'user_app_id'=>$actor->id,'workout_execution_id'=>$execution->id,
                'operation_id'=>$op,'payload_hash'=>$hash,'response'=>'{}','created_at'=>now()->utc()]);
            WorkoutPrescriptionRevision::create(['workout_execution_id'=>$execution->id,'workout_sync_operation_id'=>$operationId,
                'execution_revision'=>$execution->revision,'schema_version'=>2,'content'=>$content,'content_hash'=>hash('sha256',json_encode($content,JSON_THROW_ON_ERROR)),
                'reason'=>'athlete_exercise_replacement','created_at'=>now()->utc()]);
            $result=['ok'=>true,'data'=>app(WorkoutExecutionPresenter::class)->detail($execution)];
            DB::table('workout_sync_operations')->where('id',$operationId)->update(['response'=>json_encode($result,JSON_THROW_ON_ERROR)]);
            return $result;
        },3);
    }
}
