<?php

namespace App\Http\Controllers\Api\V1\Coach;

use App\Enums\TrainingSectionResultType;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\GroupTrainingAssignment;
use App\Models\TrainingGoalCatalog;
use App\Models\TrainingSection;
use App\Models\TrainingSession;
use App\Models\TrainingTypeCatalog;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TrainingController extends Controller
{
    public function meta(Request $request)
    {
        $coachId = $request->user()->id;

        return response()->json([
            'ok' => true,
            'data' => [
                'clients' => Client::query()
                    ->where('coach_id', $coachId)
                    ->where('is_active', true)
                    ->orderBy('first_name')
                    ->get(['id', 'first_name', 'last_name', 'email'])
                    ->map(fn ($client) => [
                        'id' => $client->id,
                        'label' => $client->full_name ?: $client->email,
                        'email' => $client->email,
                    ]),
                'groups' => \App\Models\Group::query()
                    ->where('coach_id', $coachId)
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn ($group) => [
                        'id' => $group->id,
                        'label' => $group->name,
                    ]),
                'types' => TrainingTypeCatalog::query()
                    ->where('coach_id', $coachId)
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'name']),
                'goals' => TrainingGoalCatalog::query()
                    ->where('coach_id', $coachId)
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'name']),
                'units' => Unit::query()
                    ->whereNull('coach_id')
                    ->where('is_active', true)
                    ->orderBy('result_type')
                    ->orderBy('name')
                    ->get(['id', 'result_type', 'name', 'symbol', 'code']),
                'result_types' => TrainingSectionResultType::values(),
            ],
        ]);
    }

    public function index(Request $request)
    {
        $coachId = $request->user()->id;
        $perPage = min((int) $request->query('per_page', 15), 50);

        $query = TrainingSession::query()
            ->where('coach_id', $coachId)
            ->withCount(['sections', 'assignments'])
            ->orderByDesc('scheduled_at')
            ->orderByDesc('id');

        if ($request->filled('date')) {
            $query->whereDate('scheduled_at', $request->query('date'));
        }

        if ($request->filled('visibility')) {
            $query->where('visibility', $request->query('visibility'));
        }

        return response()->json([
            'ok' => true,
            'data' => $query->paginate($perPage)->through(fn (TrainingSession $training) => $this->trainingPayload($training)),
        ]);
    }

    public function store(Request $request)
    {
        $coachId = $request->user()->id;
        $data = $this->validatedTrainingData($request, $coachId, true);

        $training = DB::transaction(function () use ($coachId, $data) {
            $training = TrainingSession::create($this->trainingHeaderPayload($coachId, $data));

            $this->syncSections($training, $data['sections'] ?? []);
            $this->syncAssignments($training, $data['assigned_client_ids'] ?? [], $data['assigned_group_ids'] ?? []);

            return $training;
        });

        $training->load(['sections.unit', 'assignments.client']);

        return response()->json([
            'ok' => true,
            'message' => 'Entrenamiento creado correctamente.',
            'data' => $this->trainingPayload($training, true),
        ], 201);
    }

    public function show(Request $request, TrainingSession $training)
    {
        $this->authorizeTraining($request, $training);

        $training->load(['sections.unit', 'assignments.client']);

        return response()->json([
            'ok' => true,
            'data' => $this->trainingPayload($training, true),
        ]);
    }

    public function update(Request $request, TrainingSession $training)
    {
        $this->authorizeTraining($request, $training);

        $coachId = $request->user()->id;
        $data = $this->validatedTrainingData($request, $coachId, false);

        DB::transaction(function () use ($training, $coachId, $data) {
            $training->update($this->trainingHeaderPayload($coachId, $data, $training));

            if (array_key_exists('sections', $data)) {
                $this->syncSections($training, $data['sections'] ?? []);
            }

            if (array_key_exists('assigned_client_ids', $data)) {
                $this->syncAssignments($training, $data['assigned_client_ids'] ?? [], $data['assigned_group_ids'] ?? []);
            } elseif (($data['visibility'] ?? $training->visibility) === 'free') {
                $this->syncAssignments($training, [], []);
            }
        });

        $training->refresh()->load(['sections.unit', 'assignments.client']);

        return response()->json([
            'ok' => true,
            'message' => 'Entrenamiento actualizado correctamente.',
            'data' => $this->trainingPayload($training, true),
        ]);
    }

    public function destroy(Request $request, TrainingSession $training)
    {
        $this->authorizeTraining($request, $training);

        $training->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Entrenamiento eliminado correctamente.',
        ]);
    }

    private function validatedTrainingData(Request $request, int $coachId, bool $creating): array
    {
        $sectionRule = $creating ? ['required', 'array', 'min:1'] : ['sometimes', 'array', 'min:1'];

        $data = $request->validate([
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:150'],
            'scheduled_at' => [$creating ? 'required' : 'sometimes', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            'level' => [$creating ? 'required' : 'sometimes', Rule::in(['beginner', 'intermediate', 'advanced'])],
            'goal' => ['nullable', Rule::in(['strength', 'cardio', 'technique', 'mobility', 'mixed'])],
            'type' => ['nullable', Rule::in(['fitness', 'functional_fitness', 'weightlifting', 'home_training'])],
            'training_goal_catalog_id' => [
                'nullable',
                'integer',
                Rule::exists('training_goal_catalogs', 'id')->where(fn ($q) => $q->where('coach_id', $coachId)),
            ],
            'training_type_catalog_id' => [
                'nullable',
                'integer',
                Rule::exists('training_type_catalogs', 'id')->where(fn ($q) => $q->where('coach_id', $coachId)),
            ],
            'visibility' => [$creating ? 'required' : 'sometimes', Rule::in(['free', 'assigned'])],
            'notes' => ['nullable', 'string'],
            'tag_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'assigned_client_ids' => ['nullable', 'array'],
            'assigned_client_ids.*' => [
                'integer',
                Rule::exists('clients', 'id')->where(fn ($q) => $q->where('coach_id', $coachId)),
            ],
            'assigned_group_ids' => ['nullable', 'array'],
            'assigned_group_ids.*' => [
                'integer',
                Rule::exists('groups', 'id')->where(fn ($q) => $q->where('coach_id', $coachId)),
            ],
            'sections' => $sectionRule,
            'sections.*.id' => ['nullable', 'integer'],
            'sections.*.name' => ['required_with:sections', 'string', 'max:100'],
            'sections.*.description' => ['nullable', 'string'],
            'sections.*.video_url' => ['nullable', 'url', 'max:255'],
            'sections.*.result_type' => ['nullable', Rule::in(array_merge(['none'], TrainingSectionResultType::values()))],
            'sections.*.unit_id' => ['nullable', 'integer', 'exists:units,id'],
        ]);

        $visibility = $data['visibility'] ?? null;
        if (
            $visibility === 'assigned'
            && empty($data['assigned_client_ids'])
            && empty($data['assigned_group_ids'])
        ) {
            validator([], [])->after(function ($validator) {
                $validator->errors()->add('assigned_client_ids', 'Debes asignar al menos un cliente o grupo si el entrenamiento es assigned.');
            })->validate();
        }

        return $data;
    }

    private function trainingHeaderPayload(int $coachId, array $data, ?TrainingSession $training = null): array
    {
        return [
            'coach_id' => $coachId,
            'title' => $data['title'] ?? $training?->title,
            'scheduled_at' => $data['scheduled_at'] ?? $training?->scheduled_at,
            'duration_minutes' => $data['duration_minutes'] ?? $training?->duration_minutes,
            'level' => $data['level'] ?? $training?->level ?? 'beginner',
            'goal' => $data['goal'] ?? $training?->goal ?? 'mixed',
            'type' => $data['type'] ?? $training?->type ?? 'fitness',
            'training_goal_catalog_id' => $data['training_goal_catalog_id'] ?? $training?->training_goal_catalog_id,
            'training_type_catalog_id' => $data['training_type_catalog_id'] ?? $training?->training_type_catalog_id,
            'visibility' => $data['visibility'] ?? $training?->visibility ?? 'assigned',
            'notes' => $data['notes'] ?? $training?->notes,
            'tag_color' => $data['tag_color'] ?? $training?->tag_color,
        ];
    }

    private function syncSections(TrainingSession $training, array $sections): void
    {
        $existingIds = $training->sections()->pluck('id')->all();
        $sentIds = collect($sections)->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();
        $deleteIds = array_diff($existingIds, $sentIds);

        if (!empty($deleteIds)) {
            TrainingSection::query()
                ->where('training_session_id', $training->id)
                ->whereIn('id', $deleteIds)
                ->delete();
        }

        TrainingSection::query()
            ->where('training_session_id', $training->id)
            ->update(['order' => DB::raw('`order` + 1000')]);

        foreach (array_values($sections) as $index => $sectionData) {
            $resultType = $sectionData['result_type'] ?? 'none';
            $acceptsResults = $resultType !== 'none';

            $payload = [
                'order' => $index + 1,
                'name' => $sectionData['name'],
                'description' => $sectionData['description'] ?? null,
                'video_url' => $sectionData['video_url'] ?? null,
                'accepts_results' => $acceptsResults,
                'result_type' => $acceptsResults ? $resultType : null,
                'unit_id' => $acceptsResults ? ($sectionData['unit_id'] ?? null) : null,
            ];

            $section = null;
            if (!empty($sectionData['id'])) {
                $section = TrainingSection::query()
                    ->where('training_session_id', $training->id)
                    ->where('id', $sectionData['id'])
                    ->first();
            }

            if ($section) {
                $section->update($payload);
            } else {
                $training->sections()->create($payload);
            }
        }
    }

    private function syncAssignments(TrainingSession $training, array $clientIds, array $groupIds = []): void
    {
        if ($training->visibility === 'free') {
            $clientIds = [];
            $groupIds = [];
        }

        $scheduledFor = optional($training->scheduled_at)->toDateString() ?? now()->toDateString();

        $training->assignedClients()->sync(
            collect($clientIds)
                ->unique()
                ->mapWithKeys(fn ($id) => [
                    (int) $id => [
                        'status' => 'scheduled',
                        'scheduled_for' => $scheduledFor,
                    ],
                ])
                ->all()
        );

        $groupIds = collect($groupIds)->unique()->map(fn ($id) => (int) $id)->values()->all();
        $existingGroupIds = GroupTrainingAssignment::query()
            ->where('training_session_id', $training->id)
            ->pluck('group_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $deleteGroupIds = array_diff($existingGroupIds, $groupIds);
        if (!empty($deleteGroupIds)) {
            GroupTrainingAssignment::query()
                ->where('training_session_id', $training->id)
                ->whereIn('group_id', $deleteGroupIds)
                ->delete();
        }

        foreach (array_diff($groupIds, $existingGroupIds) as $groupId) {
            GroupTrainingAssignment::create([
                'group_id' => $groupId,
                'training_session_id' => $training->id,
                'scheduled_for' => $scheduledFor,
                'notes' => null,
            ]);
        }

        GroupTrainingAssignment::query()
            ->where('training_session_id', $training->id)
            ->whereIn('group_id', $groupIds)
            ->update(['scheduled_for' => $scheduledFor]);

        GroupTrainingAssignment::query()
            ->where('training_session_id', $training->id)
            ->when(!empty($groupIds), fn ($query) => $query->whereNotIn('group_id', $groupIds))
            ->delete();
    }

    private function authorizeTraining(Request $request, TrainingSession $training): void
    {
        abort_unless((int) $training->coach_id === (int) $request->user()->id, 403);
    }

    private function trainingPayload(TrainingSession $training, bool $includeDetails = false): array
    {
        $payload = [
            'id' => $training->id,
            'title' => $training->title,
            'scheduled_at' => optional($training->scheduled_at)->toDateString(),
            'duration_minutes' => $training->duration_minutes,
            'level' => $training->level,
            'goal' => $training->goal,
            'type' => $training->type,
            'training_goal_catalog_id' => $training->training_goal_catalog_id,
            'training_type_catalog_id' => $training->training_type_catalog_id,
            'visibility' => $training->visibility,
            'notes' => $training->notes,
            'tag_color' => $training->tag_color,
            'sections_count' => $training->sections_count ?? $training->sections?->count(),
            'assignments_count' => $training->assignments_count ?? $training->assignments?->count(),
        ];

        if ($includeDetails) {
            $payload['sections'] = $training->sections->map(fn ($section) => [
                'id' => $section->id,
                'order' => $section->order,
                'name' => $section->name,
                'description' => $section->description,
                'video_url' => $section->video_url,
                'accepts_results' => (bool) $section->accepts_results,
                'result_type' => $section->result_type ?? 'none',
                'unit_id' => $section->unit_id,
                'unit' => $section->unit ? [
                    'id' => $section->unit->id,
                    'name' => $section->unit->name,
                    'symbol' => $section->unit->symbol,
                    'code' => $section->unit->code,
                ] : null,
            ])->values();

            $payload['assigned_clients'] = $training->assignments->map(fn ($assignment) => [
                'id' => $assignment->client_id,
                'assignment_id' => $assignment->id,
                'status' => $assignment->status,
                'scheduled_for' => optional($assignment->scheduled_for)->toDateString(),
                'name' => $assignment->client?->full_name,
                'email' => $assignment->client?->email,
            ])->values();

            $payload['assigned_groups'] = GroupTrainingAssignment::query()
                ->where('training_session_id', $training->id)
                ->with('group:id,name')
                ->get()
                ->map(fn ($assignment) => [
                    'id' => $assignment->group_id,
                    'assignment_id' => $assignment->id,
                    'scheduled_for' => optional($assignment->scheduled_for)->toDateString(),
                    'name' => $assignment->group?->name,
                ])
                ->values();
        }

        return $payload;
    }
}
