<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\User;
use App\Models\UserApp;
use App\Models\WorkoutExercise;
use App\Models\WorkoutExecution;
use App\Services\WorkoutExecutionPresenter;
use App\Services\WorkoutMaxes;
use App\Services\WorkoutProgress;
use App\Services\WorkoutOwnership;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkoutProgressController extends Controller {
    public function athleteExercises(Request $request) {
        $ownership=app(WorkoutOwnership::class);$owner=$ownership->athlete($request);$items=[];
        foreach(WorkoutExercise::whereNull('coach_id')->where('is_active',true)->where('review_status','reviewed')->with('terms')->get() as $e)
            $items[$e->id]=app(\App\Services\WorkoutExerciseCatalog::class)->payload($e,null);
        foreach($ownership->data(WorkoutExecution::query(),$owner)->cursor() as $execution)foreach($execution->current_prescription->content['sections'] as $section)foreach($section['exercises'] as $e){
            $id=(int)($e['workout_exercise_id']??0);if(!$id||isset($items[$id]))continue;
            $items[$id]=['id'=>$id,'code'=>$e['catalog']['code']??'history_'.$id,'names'=>['es'=>$e['name']],'load_mode'=>$e['catalog']['load_mode']??$e['load_mode']??'unknown',
                'fatigue'=>'low','unilateral'=>$e['catalog']['unilateral']??false,'variant_of_id'=>null,'review_status'=>'historical','is_active'=>true,'scope'=>'history','can_edit'=>false,'notes'=>null,'terms'=>[]];
        }
        foreach(['workout_max_records','workout_exercise_goals'] as $table)foreach($ownership->data(DB::table($table),$owner)->distinct()->pluck('workout_exercise_id') as $id){
            if(isset($items[$id]))continue;$e=WorkoutExercise::find($id);if(!$e)continue;
            $items[$id]=['id'=>$e->id,'code'=>$e->code,'names'=>$e->names,'load_mode'=>$e->load_mode,'fatigue'=>$e->fatigue,'unilateral'=>$e->unilateral,
                'variant_of_id'=>$e->variant_of_id,'review_status'=>'historical','is_active'=>true,'scope'=>'history','can_edit'=>false,'notes'=>null,'terms'=>[]];
        }
        return response()->json(['ok'=>true,'data'=>array_values($items)]);
    }
    private function athlete(Request $request): UserApp {
        return app(WorkoutOwnership::class)->athlete($request);
    }
    private function coach(Request $request,Client $client): Client {
        abort_unless($request->user() instanceof User && (int)$client->coach_id===(int)$request->user()->id,404); return $client;
    }
    private function exercise(Request $request,Client|UserApp $client,bool $writing=false): WorkoutExercise {
        $data=$request->validate(['exercise_id'=>['required','integer','min:1']]);
        return app(WorkoutOwnership::class)->exercise($client,(int)$data['exercise_id'],$writing);
    }
    public function athleteProgress(Request $request) {return response()->json(['data'=>app(WorkoutProgress::class)->forExercise($client=$this->athlete($request),$this->exercise($request,$client))]);}
    public function coachProgress(Request $request,Client $client) {return response()->json(['data'=>app(WorkoutProgress::class)->forExercise($this->coach($request,$client),$this->exercise($request,$client))]);}
    public function athleteGoal(Request $request) {return $this->goal($request,$this->athlete($request));}
    public function coachGoal(Request $request,Client $client) {return $this->goal($request,$this->coach($request,$client));}
    private function goal(Request $request,Client|UserApp $client) {
        $exerciseId=(int)$request->validate(['exercise_id'=>['required','integer','min:1']])['exercise_id'];
        $data=$request->validate(['request_id'=>['required','uuid'],'scheduled_for'=>['required','date_format:Y-m-d'],
            'load'=>['required','numeric','gt:0','max:10000','regex:/^\d{1,5}(?:\.\d{1,2})?$/'],'unit'=>['required','in:kg,lb'],
            'reps'=>['required','integer','min:1','max:10'],'rir'=>['required','numeric','min:0','max:3','regex:/^\d(?:\.\d)?$/'],
            'notes'=>['nullable','string','max:2000']]);
        abort_if($data['reps']+$data['rir']>10,422,'La proyección MVP admite hasta 10 repeticiones efectivas.');
        $actor=$request->user();
        $ownership=app(WorkoutOwnership::class);
        $payload=['workout_exercise_id'=>$exerciseId,'scheduled_for'=>$data['scheduled_for'],
            'load_original'=>number_format((float)$data['load'],2,'.',''),'unit'=>$data['unit'],'load_kg'=>app(WorkoutMaxes::class)->kg($data['load'],$data['unit']),
            'reps'=>(int)$data['reps'],'rir'=>number_format((float)$data['rir'],1,'.',''),'notes'=>$data['notes']??null,
            'user_app_id'=>$actor instanceof UserApp ? $actor->id : null,'recorded_by_coach_id'=>$actor instanceof User ? $actor->id : null];
        $payload=$payload+$ownership->fields($client);
        $hash=hash('sha256',json_encode($payload,JSON_THROW_ON_ERROR));
        [$goal,$created]=DB::transaction(function() use($client,$data,$payload,$hash,$ownership,$request,$exerciseId) {
            $ownership->lock($client);
            $existing=$ownership->data(DB::table('workout_exercise_goals'),$client)->where('request_id',strtolower($data['request_id']))->first();
            if($existing){
                // Old fingerprints include the historical coach; preserve their bytes on replay.
                $legacy=['coach_id'=>$existing->coach_id]+array_diff_key($payload,array_flip(['owner_user_app_id','data_scope','client_id','coach_id']));
                $legacyHash=hash('sha256',json_encode($legacy,JSON_THROW_ON_ERROR));
                abort_unless(hash_equals($existing->fingerprint,$hash)||hash_equals($existing->fingerprint,$legacyHash),409,'Este identificador ya tiene otra meta.');return [$existing,false];
            }
            $request->validate(['scheduled_for'=>['after:today','before_or_equal:'.now()->addYear()->toDateString()]]);
            $exercise=$ownership->exercise($client,$exerciseId,true);
            abort_unless($exercise->is_active && $exercise->load_mode==='total_barbell',422,'Meta disponible solo para barra compatible.');
            $id=DB::table('workout_exercise_goals')->insertGetId($payload+['request_id'=>strtolower($data['request_id']),'fingerprint'=>$hash,'created_at'=>now()->utc()]);
            return [DB::table('workout_exercise_goals')->find($id),true];
        });
        return response()->json(['data'=>$goal],$created?201:200);
    }
    public function sessions(Request $request,Client $client) {
        $this->coach($request,$client); $presenter=app(WorkoutExecutionPresenter::class);
        $sessions=WorkoutExecution::where('data_scope',WorkoutOwnership::ASSIGNED)->where('client_id',$client->id)->where('coach_id',$client->coach_id)->orderByDesc('started_at')->paginate(20);
        $sessions->through(fn($execution)=>$presenter->header($execution)+['title'=>$execution->current_prescription->content['title']??'Sesión']);
        return response()->json(['data'=>$sessions]);
    }
    public function session(Request $request,Client $client,string $execution) {
        $this->coach($request,$client);
        $session=WorkoutExecution::where('data_scope',WorkoutOwnership::ASSIGNED)->where('client_id',$client->id)->where('coach_id',$client->coach_id)->where('uuid',$execution)->firstOrFail();
        return response()->json(['data'=>app(WorkoutExecutionPresenter::class)->detail($session)]);
    }
}
