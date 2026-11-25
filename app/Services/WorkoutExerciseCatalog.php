<?php
namespace App\Services;

use App\Models\WorkoutExercise;
use App\Models\WorkoutTaxonomyTerm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class WorkoutExerciseCatalog
{
    private const FILTERS = ['regions' => 'region', 'goals' => 'goal', 'levels' => 'level', 'patterns' => 'pattern', 'muscles' => 'muscle', 'equipment' => 'equipment', 'demands' => 'demand'];
    public function search(Request $request, ?int $coachId, bool $coach): array
    {
        $rules = ['q' => ['nullable', 'string', 'max:120'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'equipment_filter' => ['sometimes', 'boolean'], 'include_inactive' => $coach ? ['sometimes', 'boolean'] : ['prohibited'],
            'review_status' => ['sometimes', 'in:pending,reviewed']];
        foreach (self::FILTERS as $key => $kind) {
            $rules[$key] = ['sometimes', 'array', 'max:50'];
            $rules[$key.'.*'] = ['required', 'string', 'distinct', Rule::exists('workout_taxonomy_terms', 'code')->where('kind', $kind)];
        }
        $data = $request->validate($rules);
        $query = $coach ? WorkoutExercise::query()->visibleForCoach((int)$coachId)->with('terms')
            : WorkoutExercise::whereNull('coach_id')->where('review_status','reviewed')->with('terms');
        if (!($data['include_inactive'] ?? false)) $query->where('is_active', true);
        if (!empty($data['review_status'])) $query->where('review_status', $data['review_status']);
        if (isset($data['q']) && trim($data['q']) !== '') {
            $needle = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($data['q'])).'%';
            $query->where(fn ($q) => $q->where('code', 'like', $needle)->orWhere('names->es', 'like', $needle)->orWhere('names->en', 'like', $needle));
        }
        foreach (self::FILTERS as $key => $kind) {
            if ($kind === 'equipment' || empty($data[$key])) continue;
            $query->whereHas('terms', fn ($q) => $q->where('kind', $kind)->whereIn('code', $data[$key]));
        }
        if (array_key_exists('equipment', $data) || ($data['equipment_filter'] ?? false)) {
            $available = array_unique(array_merge(['bodyweight'], $data['equipment'] ?? []));
            // Every required item must be available: OR semantics would suggest unusable exercises.
            $query->whereDoesntHave('terms', fn ($q) => $q->where('kind', 'equipment')->whereNotIn('code', $available));
        }
        $page = $query->orderBy('code')->paginate($data['per_page'] ?? 50);
        return ['data' => $page->getCollection()->map(fn ($exercise) => $this->payload($exercise, $coach ? $coachId : null)),
            'meta' => ['total' => $page->total(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage()]];
    }
    public function meta(): array
    {
        return WorkoutTaxonomyTerm::orderBy('kind')->orderBy('code')->get()->groupBy('kind')->map(fn ($terms) => $terms->map(fn ($t) => [
            'code' => $t->code, 'names' => $t->names,
        ])->values())->all();
    }
    public function payload(WorkoutExercise $exercise, ?int $coachId): array
    {
        $exercise->loadMissing('terms');
        return [
            'id' => $exercise->id, 'code' => $exercise->code, 'names' => $exercise->names, 'variant_of_id' => $exercise->variant_of_id,
            'scope' => $exercise->coach_id === null ? 'global' : 'coach', 'can_edit' => $coachId !== null && $exercise->coach_id === $coachId,
            'load_mode' => $exercise->load_mode, 'fatigue' => $exercise->fatigue, 'unilateral' => $exercise->unilateral,
            'is_active' => $exercise->is_active, 'review_status' => $exercise->review_status, 'notes' => $exercise->notes,
            'terms' => $exercise->terms->map(fn ($t) => ['kind' => $t->kind, 'code' => $t->code, 'names' => $t->names, 'role' => $t->pivot->role])->values()->all(),
        ];
    }
    public function save(Request $request, int $coachId, ?WorkoutExercise $exercise = null): WorkoutExercise
    {
        $rules = [
            'code' => $exercise ? ['prohibited'] : ['required', 'string', 'max:80', 'regex:/^[a-z][a-z0-9_]*$/'],
            'coach_id' => ['prohibited'], 'names' => ['required', 'array:es,en'], 'names.es' => ['required', 'string', 'max:160'], 'names.en' => ['nullable', 'string', 'max:160'],
            'variant_of_id' => $exercise ? ['prohibited'] : ['nullable', 'integer', Rule::exists('workout_exercises', 'id')->where(fn ($q) => $q->whereNull('coach_id')->orWhere('coach_id', $coachId))],
            'load_mode' => ['required', 'in:total_barbell,per_dumbbell,external_total,machine_indicated,bodyweight'],
            'fatigue' => ['required', 'in:low,moderate,high'], 'unilateral' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'], 'review_status' => ['required', 'in:pending,reviewed'], 'notes' => ['nullable', 'string', 'max:4000'],
        ];
        $fields = ['regions' => 'region', 'goals' => 'goal', 'levels' => 'level', 'patterns' => 'pattern', 'equipment' => 'equipment', 'primary_muscles' => 'muscle', 'secondary_muscles' => 'muscle', 'demands' => 'demand'];
        foreach ($fields as $field => $kind) {
            $rules[$field] = ['required', 'array', $field === 'secondary_muscles' ? 'min:0' : 'min:1', 'max:20'];
            $rules[$field.'.*'] = ['required', 'string', 'distinct', Rule::exists('workout_taxonomy_terms', 'code')->where('kind', $kind)];
        }
        // Empty secondary_muscles is valid; Laravel required rejects an empty array.
        $rules['secondary_muscles'] = ['present', 'array', 'max:20'];
        $rules['demands'] = ['present', 'array', 'max:20'];
        $validator = Validator::make($request->all(), $rules);
        $validator->after(function ($v) use ($request) {
            $main = $request->input('primary_muscles'); $secondary = $request->input('secondary_muscles');
            if (is_array($main) && is_array($secondary) && array_intersect($main, $secondary)) $v->errors()->add('secondary_muscles', 'Un músculo no puede ser principal y secundario a la vez.');
        });
        $data = $validator->validate();
        return DB::transaction(function () use ($data, $fields, $coachId, $exercise) {
            $attributes = array_intersect_key($data, array_flip(['names', 'load_mode', 'fatigue', 'unilateral', 'is_active', 'review_status', 'notes']));
            if ($exercise) $exercise->update($attributes);
            else {
                $code = 'coach_'.$coachId.'_'.$data['code'];
                abort_if(WorkoutExercise::where('code', $code)->exists(), 422, 'Ya existe ese código en tu catálogo.');
                $exercise = WorkoutExercise::create($attributes + ['code' => $code, 'coach_id' => $coachId, 'variant_of_id' => $data['variant_of_id'] ?? null]);
            }
            $pivot = [];
            foreach ($fields as $field => $kind) foreach ($data[$field] as $code) {
                $term = WorkoutTaxonomyTerm::where('kind', $kind)->where('code', $code)->firstOrFail();
                $pivot[$term->id] = ['role' => $field === 'primary_muscles' ? 'primary' : ($field === 'secondary_muscles' ? 'secondary' : ($kind === 'equipment' ? 'required' : 'classification'))];
            }
            $exercise->terms()->sync($pivot);
            return $exercise->refresh();
        });
    }
}
