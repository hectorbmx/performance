<?php
namespace App\Services;

use App\Models\User;
use App\Models\WorkoutExercise;
use App\Models\WorkoutTaxonomyTerm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PlatformWorkoutCatalog
{
    public const FIELDS=['regions'=>'region','goals'=>'goal','levels'=>'level','patterns'=>'pattern','equipment'=>'equipment','primary_muscles'=>'muscle','secondary_muscles'=>'muscle','demands'=>'demand'];
    public function fingerprint(WorkoutExercise $exercise): string
    {
        $exercise->load('terms');
        return hash('sha256',json_encode([$exercise->getAttributes(),$exercise->terms->sortBy('id')->map(fn($t)=>[$t->id,$t->pivot->role])->values()->all()],JSON_THROW_ON_ERROR));
    }
    public function save(Request $request,WorkoutExercise $exercise): WorkoutExercise
    {
        $actor=$request->user();abort_unless($actor instanceof User && $actor->hasRole('admin'),403);
        abort_unless($exercise->coach_id===null,404);
        foreach(self::FIELDS as $field=>$kind)if($request->exists($field)&&$request->input($field)===null)$request->merge([$field=>[]]);
        $rules=['fingerprint'=>['required','string','size:64'],'action'=>['required','in:draft,publish,withdraw'],
            'reason'=>['required','string','min:5','max:2000'],'review_confirmed'=>['exclude_unless:action,publish','required','accepted'],
            'names'=>['required','array:es,en'],'names.es'=>['required','string','max:160'],'names.en'=>['nullable','string','max:160'],
            'load_mode'=>['required','in:total_barbell,per_dumbbell,external_total,machine_indicated,bodyweight'],
            'fatigue'=>['required','in:low,moderate,high'],'unilateral'=>['required','boolean'],'is_active'=>['required','boolean'],
            'notes'=>['nullable','string','max:4000'],'coach_id'=>['prohibited'],'code'=>['prohibited'],'variant_of_id'=>['prohibited']];
        foreach(self::FIELDS as $field=>$kind){
            $rules[$field]=['present','array',$field==='secondary_muscles'||$field==='demands'?'min:0':'min:1','max:20'];
            $rules[$field.'.*']=['string','distinct',Rule::exists('workout_taxonomy_terms','code')->where('kind',$kind)];
        }
        $data=$request->validate($rules);
        abort_if(array_intersect($data['primary_muscles'],$data['secondary_muscles']),422,'Un músculo no puede ser principal y secundario.');
        abort_if($data['action']==='publish'&&!$data['is_active'],422,'Activa el ejercicio antes de publicarlo.');
        return DB::transaction(function()use($exercise,$data,$actor){
            $locked=WorkoutExercise::whereNull('coach_id')->lockForUpdate()->findOrFail($exercise->id);
            abort_unless(hash_equals($this->fingerprint($locked),$data['fingerprint']),409,'El ejercicio cambió. Recarga antes de guardar.');
            $catalog=app(WorkoutExerciseCatalog::class);$before=$catalog->payload($locked,null);
            $locked->fill(array_intersect_key($data,array_flip(['names','load_mode','fatigue','unilateral','is_active','notes'])));
            $locked->review_status=$data['action']==='publish'?'reviewed':'pending';$locked->save();
            $pivot=[];
            foreach(self::FIELDS as $field=>$kind)foreach($data[$field] as $code){
                $term=WorkoutTaxonomyTerm::where('kind',$kind)->where('code',$code)->firstOrFail();
                $pivot[$term->id]=['role'=>$field==='primary_muscles'?'primary':($field==='secondary_muscles'?'secondary':($kind==='equipment'?'required':'classification'))];
            }
            $locked->terms()->sync($pivot);$locked->refresh();
            DB::table('workout_catalog_reviews')->insert(['workout_exercise_id'=>$locked->id,'reviewer_id'=>$actor->id,'action'=>$data['action'],
                'reason'=>$data['reason'],'before'=>json_encode($before,JSON_THROW_ON_ERROR),'after'=>json_encode($catalog->payload($locked,null),JSON_THROW_ON_ERROR),'created_at'=>now()]);
            return $locked;
        });
    }
}
