<?php
namespace App\Services;

use App\Models\Client;
use App\Models\UserApp;
use App\Models\WorkoutExecution;
use App\Models\WorkoutExercise;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkoutOwnership
{
    public const PERSONAL='personal_gym';
    public const ASSIGNED='coach_assigned';
    public const LEGACY='legacy_coach';

    public function athlete(Request $request): UserApp
    {
        $actor=$request->user();
        abort_unless($actor instanceof UserApp && $actor->is_active,403,'Cuenta de atleta activa requerida.');
        return $actor;
    }
    public function requireCreation(UserApp $actor): void
    {
        abort_unless($actor->is_active && config('workouts.generator_enabled'),403,'GYM está temporalmente desactivado en la plataforma.');
    }
    public function fields(Client|UserApp $owner): array
    {
        return $owner instanceof UserApp
            ? ['owner_user_app_id'=>$owner->id,'data_scope'=>self::PERSONAL,'client_id'=>null,'coach_id'=>null]
            : ['owner_user_app_id'=>null,'data_scope'=>self::LEGACY,'client_id'=>$owner->id,'coach_id'=>$owner->coach_id];
    }
    public function data($query, Client|UserApp $owner, string $prefix='')
    {
        if ($owner instanceof UserApp) return $query->where($prefix.'data_scope',self::PERSONAL)->where($prefix.'owner_user_app_id',$owner->id);
        return $query->where($prefix.'data_scope','!=',self::PERSONAL)->where($prefix.'client_id',$owner->id)->where($prefix.'coach_id',$owner->coach_id);
    }
    public function lock(Client|UserApp $owner): void
    {
        $model=$owner instanceof UserApp ? UserApp::class : Client::class;
        $fresh=$model::whereKey($owner->id)->lockForUpdate()->firstOrFail();
        abort_unless($fresh->is_active,403,'Cuenta inactiva.');
        $owner->refresh();
    }
    public function executions(UserApp $actor): Builder
    {
        abort_unless($actor->is_active,403,'Cuenta inactiva.');
        $client=$actor->client;
        return WorkoutExecution::query()->where(function($q) use($actor,$client) {
            $q->where(fn($personal)=>$personal->where('data_scope',self::PERSONAL)->where('owner_user_app_id',$actor->id));
            if ($client?->is_active) $q->orWhere(fn($legacy)=>$legacy->where('data_scope','!=',self::PERSONAL)
                ->where('client_id',$client->id)->where('coach_id',$client->coach_id));
        });
    }
    public function catalog(Client|UserApp $owner): Builder
    {
        return $owner instanceof UserApp ? WorkoutExercise::whereNull('coach_id') : WorkoutExercise::visibleForCoach((int)$owner->coach_id);
    }
    public function exercise(Client|UserApp $owner,int $id,bool $writing=false): WorkoutExercise
    {
        if ($owner instanceof Client) return $this->catalog($owner)->findOrFail($id);
        $exercise=WorkoutExercise::findOrFail($id);
        if ($writing) {
            $this->requireCreation($owner);
            abort_unless($exercise->coach_id===null && $exercise->is_active && $exercise->review_status==='reviewed',404);
        } elseif ($exercise->coach_id!==null) {
            // Historical identifiers are readable only through this athlete's proven records.
            $known=$this->data(DB::table('workout_max_records'),$owner)->where('workout_exercise_id',$id)->exists()
                || $this->data(DB::table('workout_exercise_goals'),$owner)->where('workout_exercise_id',$id)->exists();
            if (!$known) {
                foreach ($this->data(WorkoutExecution::query(),$owner)->with('prescription')->cursor() as $execution) {
                    foreach ($execution->current_prescription->content['sections'] as $section) foreach ($section['exercises'] as $item) {
                        if ((int)($item['workout_exercise_id']??0)===$id) {$known=true;break 3;}
                    }
                }
            }
            abort_unless($known,404);
        }
        return $exercise;
    }
}
