<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\User;
use App\Models\UserApp;
use App\Services\WorkoutRoutineRecommendations;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkoutGeneratorController extends Controller
{
    private function athlete(Request $request): UserApp {
        return app(\App\Services\WorkoutOwnership::class)->athlete($request);
    }
    private function coachClient(Request $request, Client $client): void {
        abort_unless($request->user() instanceof User, 403);
        abort_unless((int) $client->coach_id === (int) $request->user()->id, 404);
    }
    private function settingsPayload(Client|UserApp $client,WorkoutRoutineRecommendations $service):array {
        return $service->settings($client) + [
            'feature_enabled'=>(bool)config('workouts.generator_enabled'),
            'executions_enabled'=>(bool)config('workouts.executions_enabled'),
            'profile'=>config('workout_rules.profile'),
        ];
    }
    public function athleteSettings(Request $request, WorkoutRoutineRecommendations $service) {
        return response()->json(['ok' => true, 'data' => $this->settingsPayload($this->athlete($request),$service)]);
    }
    public function replaceExercise(Request $request,string $execution) {
        $this->athlete($request);
        return response()->json(app(\App\Services\WorkoutExerciseReplacement::class)->replace($request,$request->user()));
    }
    public function createExecution(Request $request) {
        $this->athlete($request);
        [$execution,$created] = app(\App\Services\WorkoutGeneratedExecution::class)->create($request,$request->user());
        return response()->json(['ok'=>true,'data'=>app(\App\Services\WorkoutExecutionPresenter::class)->detail($execution)],$created?201:200);
    }
    public function recommend(Request $request, WorkoutRoutineRecommendations $service) {
        return response()->json(['ok' => true, 'data' => $service->recommend($request, $this->athlete($request))]);
    }
    public function preview(Request $request, Client $client, WorkoutRoutineRecommendations $service) {
        $this->coachClient($request,$client);
        return response()->json(['ok'=>false,'code'=>'gym_coach_controls_retired','message'=>'GYM es personal del atleta; el coach no autoriza ni configura este servicio.'],410);
    }
    private function prescription(Request $request, Client|UserApp $client, WorkoutRoutineRecommendations $service, bool $preview) {
        $data = $request->validate([
            'exercise_id' => ['required', 'integer', 'min:1'], 'sets' => ['sometimes', 'integer', 'min:1', 'max:6'],
            'reps_min' => ['sometimes', 'integer', 'min:1', 'max:30'], 'reps_max' => ['sometimes', 'integer', 'min:1', 'max:30'],
            'target_rir' => ['sometimes', 'numeric', 'min:0', 'max:5', 'regex:/^\d(?:\.\d)?$/'], 'rest_seconds' => ['sometimes', 'integer', 'min:15', 'max:600'],
            'unit' => ['sometimes', 'in:kg,lb'], 'increment' => ['sometimes', \Illuminate\Validation\Rule::in(config('workout_prescription.increments'))],
            'pain_reported' => ['sometimes', 'boolean'], 'technique_issue' => ['sometimes', 'boolean'],
        ]);
        abort_if(($data['reps_min'] ?? config('workout_prescription.reps_min')) > ($data['reps_max'] ?? config('workout_prescription.reps_max')), 422, 'El rango de repeticiones es inválido.');
        $request->merge(['selected_ids' => [(int) $data['exercise_id']],'discover_equipment'=>false]);
        $service->recommend($request, $client, $preview); // Revalidate equipment, review, permissions and coach restrictions.
        $exercise = \App\Models\WorkoutExercise::findOrFail($data['exercise_id']);
        $result = app(\App\Services\WorkoutPrescription::class)->propose($client, $exercise, $data) + ['preview' => $preview];
        $uuid = (string) \Illuminate\Support\Str::uuid();
        $result['suggestion_id'] = $uuid;
        \Illuminate\Support\Facades\DB::table('workout_load_suggestions')->insert([
            'uuid' => $uuid, 'workout_exercise_id' => $exercise->id,
            'rule_version' => $result['rule_version'], 'preview' => $preview, 'parameters' => json_encode($data, JSON_THROW_ON_ERROR),
            'result' => json_encode($result, JSON_THROW_ON_ERROR), 'created_at' => now()->utc(),
        ] + app(\App\Services\WorkoutOwnership::class)->fields($client));
        return response()->json(['ok' => true, 'data' => $result]);
    }
    public function prescribe(Request $request, WorkoutRoutineRecommendations $service) {
        $actor=$this->athlete($request);
        return \Illuminate\Support\Facades\DB::transaction(function()use($actor,$request,$service){
            app(\App\Services\WorkoutOwnership::class)->lock($actor);
            return $this->prescription($request,$actor,$service,false);
        });
    }
    public function prescriptionPreview(Request $request, Client $client, WorkoutRoutineRecommendations $service) {
        $this->coachClient($request,$client);
        return response()->json(['ok'=>false,'code'=>'gym_coach_controls_retired','message'=>'GYM es personal del atleta; el coach no autoriza ni configura este servicio.'],410);
    }
    public function coachSettings(Request $request, Client $client, WorkoutRoutineRecommendations $service) {
        $this->coachClient($request,$client);
        return response()->json(['ok'=>false,'code'=>'gym_coach_controls_retired','message'=>'GYM es personal del atleta; el coach no autoriza ni configura este servicio.'],410);
    }
    public function updateSettings(Request $request, Client $client, WorkoutRoutineRecommendations $service) {
        $this->coachClient($request,$client);
        return response()->json(['ok'=>false,'code'=>'gym_coach_controls_retired','message'=>'GYM es personal del atleta; el coach no autoriza ni configura este servicio.'],410);
    }
    public function updateAthleteSettings(Request $request, WorkoutRoutineRecommendations $service) {
        $actor=$this->athlete($request);
        $data=$request->validate([
            'blocked_exercise_codes'=>['present','array','max:200'],
            'blocked_exercise_codes.*'=>['string','distinct',Rule::exists('workout_exercises','code')->whereNull('coach_id')],
            'blocked_demands'=>['present','array','max:30'],
            'blocked_demands.*'=>['string','distinct',Rule::exists('workout_taxonomy_terms','code')->where('kind','demand')],
        ]);
        \Illuminate\Support\Facades\DB::transaction(function()use($actor,$data){
            app(\App\Services\WorkoutOwnership::class)->lock($actor);
            \App\Models\AthleteGymPreference::updateOrCreate(['user_app_id'=>$actor->id],$data);
        });
        return $this->athleteSettings($request,$service);
    }
}
