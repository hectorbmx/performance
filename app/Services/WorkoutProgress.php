<?php
namespace App\Services;
use App\Models\Client;
use App\Models\UserApp;
use App\Models\WorkoutExercise;
use App\Models\WorkoutMaxRecord;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class WorkoutProgress {
    public function forExercise(Client|UserApp $client, WorkoutExercise $exercise): array {
        $maxes = app(WorkoutMaxes::class); $points = []; $seen = [];
        $records = $maxes->records($client,$exercise)->where('measured_at','<=',now())
            ->whereNotIn('id',app(WorkoutOwnership::class)->data(WorkoutMaxRecord::whereNotNull('supersedes_id'),$client)->select('supersedes_id'))->limit(200)->get();
        foreach ($records as $record) {
            if ($record->source_set_id) {
                $current = DB::table('workout_execution_sets')->find($record->source_set_id);
                if (!$current || (int)$current->revision !== (int)$record->source_revision) continue;
                $seen[$record->source_set_id] = true;
            }
            $points[] = ['kind'=>$record->kind,'value_kg'=>$record->value_kg,'date'=>$record->measured_at->toIso8601String(),
                'method'=>$record->method,'confidence'=>$record->confidence,'source'=>$record->source,'record_id'=>$record->id];
        }
        foreach ($maxes->history($client,$exercise) as $set) {
            if (isset($seen[$set->id]) || !$estimate=$maxes->estimate($set)) continue;
            $points[] = ['kind'=>'estimated','date'=>$estimate['measured_at'],'value_kg'=>$estimate['value_kg'],'method'=>$estimate['method'],
                'confidence'=>$estimate['confidence'],'source'=>'recorded_set','source_set_id'=>$set->id,'source_revision'=>$set->revision];
        }
        usort($points,fn($a,$b)=>strcmp($a['date'],$b['date']));
        $goals = app(WorkoutOwnership::class)->data(DB::table('workout_exercise_goals'),$client)
            ->where('workout_exercise_id',$exercise->id)->where('scheduled_for','>=',now()->toDateString())->orderBy('scheduled_for')->limit(100)->get()->map(function($goal) use($maxes) {
                $effective = (int)round(((int)$goal->reps+(float)$goal->rir)*10);
                $micros = $maxes->micros($goal->load_kg);
                $projected = $effective===10 ? $micros : intdiv($micros*(300+$effective)+150,300);
                return ['id'=>$goal->id,'kind'=>'goal','date'=>$goal->scheduled_for,'value_kg'=>$maxes->decimal($projected),
                    'method'=>'epley_rir_v1','confidence'=>'planned','load_original'=>$goal->load_original,'unit'=>$goal->unit,
                    'reps'=>$goal->reps,'rir'=>$goal->rir,'notes'=>$goal->notes,
                    'author'=>$goal->recorded_by_coach_id ? 'coach' : 'athlete'];
            })->all();
        return ['exercise_id'=>$exercise->id,'name'=>$exercise->names['es'],'points'=>$points,'goals'=>$goals,
            'scope'=>'exact_exercise','unit'=>'kg','history_window_days'=>config('workout_prescription.max_history_age_days'),
            'limits'=>'Estimación orientativa de baja confianza; una meta programada no predice adaptación ni garantiza un RM.'];
    }
}
