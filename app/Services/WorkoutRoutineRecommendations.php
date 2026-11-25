<?php
namespace App\Services;

use App\Models\Client;
use App\Models\WorkoutAthleteSetting;
use App\Models\WorkoutExercise;
use App\Models\UserApp;
use App\Models\AthleteGymPreference;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkoutRoutineRecommendations
{
    public function settings(Client|UserApp $client): array
    {
        if ($client instanceof UserApp) {
            $settings=AthleteGymPreference::where('user_app_id',$client->id)->first();
            return ['generator_enabled'=>(bool)config('workouts.generator_enabled'),'access_source'=>'platform',
                'blocked_exercise_codes'=>$settings?->blocked_exercise_codes ?? [],'blocked_demands'=>$settings?->blocked_demands ?? []];
        }
        $settings = WorkoutAthleteSetting::where('client_id', $client->id)->where('coach_id', $client->coach_id)->first();
        return ['generator_enabled' => $settings?->generator_enabled ?? false,
            'blocked_exercise_codes' => $settings?->blocked_exercise_codes ?? [], 'blocked_demands' => $settings?->blocked_demands ?? []];
    }
    public function recommend(Request $request, Client|UserApp $client, bool $preview = false): array
    {
        $data = $request->validate([
            'selected_ids' => ['present', 'array', 'max:6'], 'selected_ids.*' => ['integer', 'min:1', 'distinct'],
            'preferred_ids' => ['sometimes', 'array', 'max:30'], 'preferred_ids.*' => ['integer', 'min:1', 'distinct'],
            'equipment' => ['present', 'array', 'max:50'], 'equipment.*' => ['string', 'distinct', Rule::exists('workout_taxonomy_terms', 'code')->where('kind', 'equipment')],
            'exercise_count' => ['sometimes', 'integer', 'min:3', 'max:6'],
            'discover_equipment'=>['sometimes','boolean'],
            'region' => ['sometimes', 'in:lower_body,upper_body'], 'initial_muscle' => ['sometimes','nullable','string',Rule::exists('workout_taxonomy_terms','code')->where('kind','muscle')], 'goal' => ['sometimes', 'in:hypertrophy'], 'level' => ['sometimes', 'in:intermediate'],
        ]);
        $settings = $this->settings($client);
        if (!$preview) {
            abort_unless(config('workouts.generator_enabled') && $settings['generator_enabled'], 403, 'El generador no está habilitado para este atleta.');
        }
        $availableEquipment = array_unique(array_merge(['bodyweight'], $data['equipment']));
        $query = app(WorkoutOwnership::class)->catalog($client)->where('is_active', true)->with('terms');
        if (!$preview) $query->where('review_status', 'reviewed');
        foreach (array_replace(config('workout_rules.profile'), ['region'=>$data['region'] ?? 'lower_body']) as $kind => $code) $query->whereHas('terms', fn ($q) => $q->where('kind', $kind)->where('code', $code));
        $query->whereNotIn('code', $settings['blocked_exercise_codes'])
            ->whereDoesntHave('terms', fn ($q) => $q->where('kind', 'demand')->whereIn('code', $settings['blocked_demands']));
        if(!($data['discover_equipment']??false))$query->whereDoesntHave('terms',fn($q)=>$q->where('kind','equipment')->whereNotIn('code',$availableEquipment));
        $exercises = $query->orderBy('code')->get()->map(function ($exercise) {
            $terms = $exercise->terms;
            return ['id' => $exercise->id, 'code' => $exercise->code, 'names' => $exercise->names,
                'fatigue' => $exercise->fatigue, 'family' => $exercise->variant_of_id ?? $exercise->id, 'review_status' => $exercise->review_status,
                'equipment'=>$terms->where('kind','equipment')->pluck('code')->all(),
                'patterns' => $terms->where('kind', 'pattern')->pluck('code')->all(),
                'primary_muscles' => $terms->filter(fn ($term) => $term->kind === 'muscle' && $term->pivot->role === 'primary')->pluck('code')->all()];
        })->keyBy('id');
        $selected = [];
        foreach ($data['selected_ids'] as $id) {
            abort_unless($exercises->has((int) $id), 422, 'Un ejercicio elegido no está disponible con el equipo, revisión o permisos actuales.');
            abort_if(array_diff($exercises->get((int)$id)['equipment'],$availableEquipment),422,'Confirma todo el equipo requerido del ejercicio elegido.');
            $selected[] = $exercises->get((int) $id);
        }
        $preferred = array_map('intval', $data['preferred_ids'] ?? []);
        $candidates = $exercises->except(array_map('intval', $data['selected_ids']))->values()->all();
        if (!$selected && !empty($data['initial_muscle'])) {
            $candidates = array_values(array_filter($candidates, fn($e)=>in_array($data['initial_muscle'],$e['primary_muscles'],true)));
        }
        $rules = config('workout_rules');
        $ranked = app(WorkoutRecommendationEngine::class)->rank($candidates, $selected, $preferred, $rules);
        $count = $data['exercise_count'] ?? 4;
        $shortage = max(0, $count - count($selected) - count($ranked));
        return ['rule_version' => $rules['version'], 'preview' => $preview, 'selected_ids' => array_column($selected, 'id'),
            'exercise_count' => $count, 'candidates' => $ranked, 'can_add_more' => count($selected) < $count && count($ranked) > 0,
            'shortage' => $shortage, 'message' => $shortage ? 'Hay pocos candidatos con estas condiciones. Puedes continuar con menos ejercicios o revisar el equipo disponible.' : null];
    }
}
