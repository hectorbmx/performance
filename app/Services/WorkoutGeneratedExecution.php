<?php
namespace App\Services;

use App\Models\Client;
use App\Models\UserApp;
use App\Models\WorkoutExecution;
use App\Models\WorkoutExercise;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class WorkoutGeneratedExecution
{
    public function create(Request $request, UserApp $actor): array {
        $data = $request->validate([
            'client_execution_id' => ['required','uuid'], 'title' => ['required','string','max:150'],
            'equipment' => ['present','array','max:50'], 'equipment.*' => ['string','distinct',Rule::exists('workout_taxonomy_terms','code')->where('kind','equipment')],
            'region'=>['sometimes','in:lower_body,upper_body'],
            'acknowledge_short_session' => ['sometimes','boolean'],
            'exercises' => ['required','array','min:1','max:6'], 'exercises.*.exercise_id' => ['required','integer','min:1','distinct'],
            'exercises.*.sets' => ['required','integer','min:1','max:6'],
            'exercises.*.reps_min' => ['required','integer','min:1','max:30'], 'exercises.*.reps_max' => ['required','integer','min:1','max:30'],
            'exercises.*.target_rir' => ['required','numeric','min:0','max:5','regex:/^\d(?:\.\d)?$/'],
            'exercises.*.rest_seconds' => ['required','integer','min:15','max:600'],
            'exercises.*.suggestion_id' => ['nullable','uuid'],
            'exercises.*.chosen_load' => ['present','nullable','numeric','min:0','max:10000','regex:/^\d{1,5}(?:\.\d{1,2})?$/'],
            'exercises.*.unit' => ['required','in:kg,lb'], 'exercises.*.notes' => ['nullable','string','max:2000'],
        ]);
        foreach ($data['exercises'] as $item) abort_if($item['reps_min'] > $item['reps_max'],422,'El rango de repeticiones es inválido.');
        $hashData = $data; unset($hashData['client_execution_id']);
        $hash = hash('sha256','athlete_generated:'.json_encode($hashData,JSON_THROW_ON_ERROR));
        return DB::transaction(function () use ($request,$data,$actor,$hash) {
            $ownership=app(WorkoutOwnership::class);$ownership->lock($actor);$client=$actor;
            $existing = $ownership->data(WorkoutExecution::query(),$actor)->where('client_execution_id',strtolower($data['client_execution_id']))->first();
            if ($existing) {
                abort_unless(hash_equals($existing->start_fingerprint,$hash),409,'Este intento de inicio ya se usó con otro contenido.');
                return [$existing,false];
            }
            if ($actor->client_id) abort_if(WorkoutExecution::where('client_id',$actor->client_id)->where('data_scope','!=',WorkoutOwnership::PERSONAL)
                ->where('client_execution_id',strtolower($data['client_execution_id']))->exists(),409,'Este inicio anterior requiere revisar su propiedad; se conserva sin duplicarlo.');
            abort_unless(config('workouts.executions_enabled'),403,'Nuevas ejecuciones deshabilitadas.');
            $ownership->requireCreation($actor);
            $selected = array_map(fn ($item)=>(int)$item['exercise_id'],$data['exercises']);
            $eligible=WorkoutExercise::whereIn('id',$selected)->whereNull('coach_id')->where('is_active',true)->where('review_status','reviewed')->count();
            abort_if($eligible!==count($selected),409,'La propuesta requiere revisar su catálogo antes de iniciarse. Se conserva para que puedas actualizarla.');
            $validation = clone $request; $validation->merge(['selected_ids'=>$selected,'discover_equipment'=>false]);
            $recommendations = app(WorkoutRoutineRecommendations::class)->recommend($validation,$client);
            $available = count($recommendations['candidates']) + count($selected);
            if (count($selected)<3) abort_unless($available<3 && ($data['acknowledge_short_session'] ?? false),422,'Elige al menos tres ejercicios o confirma la sesión corta cuando no hay candidatos suficientes.');
            $exercises = [];
            foreach ($data['exercises'] as $input) {
                $exercises[] = $this->buildExercise($client,$input);
            }
            $content = ['title'=>trim($data['title']),'source'=>'athlete_generated','profile'=>array_replace(config('workout_rules.profile'),['region'=>$data['region'] ?? 'lower_body']),
                'equipment'=>$data['equipment'],'rule_versions'=>['recommendation'=>config('workout_rules.version'),'prescription'=>config('workout_prescription.version')],
                'sections'=>[['uuid'=>(string)Str::uuid(),'name'=>'Rutina personalizada','description'=>null,'exercises'=>$exercises]]];
            $execution = WorkoutExecution::create(['uuid'=>(string)Str::uuid(),'client_execution_id'=>strtolower($data['client_execution_id']),
                'start_fingerprint'=>$hash,'status'=>'in_progress','revision'=>1,'started_at'=>now()]+$ownership->fields($actor));
            $execution->prescription()->create(['schema_version'=>2,'content'=>$content,'content_hash'=>hash('sha256',json_encode($content,JSON_THROW_ON_ERROR)),'created_at'=>now()]);
            return [$execution,true];
        },3);
    }
    public function buildExercise(Client|UserApp $client, array $input): array {
                $ownership=app(WorkoutOwnership::class);
                $exercise = $ownership->catalog($client)->where('is_active',true)->with('terms')->lockForUpdate()->findOrFail($input['exercise_id']);
                abort_unless($exercise->review_status === 'reviewed',422,'La clasificación del ejercicio requiere revisión.');
                abort_if($input['chosen_load'] !== null && $exercise->load_mode !== 'total_barbell',422,'La carga prescrita del piloto admite solo peso total de barra.');
                $recommended = null;
                if (!empty($input['suggestion_id'])) {
                    $suggestion = $ownership->data(DB::table('workout_load_suggestions'),$client)->where('uuid',strtolower($input['suggestion_id']))
                        ->where('workout_exercise_id',$exercise->id)->where('preview',false)->first();
                    if(!$suggestion && $client instanceof UserApp) abort_if(DB::table('workout_load_suggestions')->where('uuid',strtolower($input['suggestion_id']))
                        ->where('data_scope',WorkoutOwnership::LEGACY)->exists(),409,'La sugerencia anterior requiere revisar su propiedad; la propuesta se conserva.');
                    abort_unless($suggestion,422,'La sugerencia no pertenece a este atleta y ejercicio.');
                    $recommended = json_decode($suggestion->result,true,512,JSON_THROW_ON_ERROR);
                }
                $sets = [];
                for ($number=1;$number<=$input['sets'];$number++) $sets[] = [
                    'uuid'=>(string)Str::uuid(),'set_number'=>$number,'type'=>'work',
                    'prescribed'=>['reps_min'=>(int)$input['reps_min'],'reps_max'=>(int)$input['reps_max'],'rir'=>(float)$input['target_rir'],
                        'rest_seconds'=>(int)$input['rest_seconds'],'percentage'=>null,
                        'load'=>$input['chosen_load'] === null ? null : ['value'=>(string)$input['chosen_load'],'unit'=>$input['unit'],'mode'=>'total_barbell']],
                    'recommended'=>$recommended,'notes'=>$input['notes'] ?? null,
                ];
                return ['uuid'=>(string)Str::uuid(),'workout_exercise_id'=>$exercise->id,'name'=>$exercise->names['es'],
                    'notes'=>$input['notes'] ?? null,'load_mode'=>$exercise->load_mode,
                    'catalog'=>['code'=>$exercise->code,'names'=>$exercise->names,'review_status'=>$exercise->review_status,'load_mode'=>$exercise->load_mode,'unilateral'=>$exercise->unilateral,
                        'terms'=>$exercise->terms->map(fn($term)=>['kind'=>$term->kind,'code'=>$term->code,'role'=>$term->pivot->role])->values()->all()],
                    'sets'=>$sets];

    }

}
