<?php

namespace App\Http\Controllers\Api\V1\Coach;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GroupController extends Controller
{
    public function index(Request $request)
    {
        $coachId = $request->user()->id;
        $q = trim((string) $request->query('q', ''));
        $perPage = min((int) $request->query('per_page', 25), 50);

        $groups = Group::query()
            ->where('coach_id', $coachId)
            ->when($q !== '', fn ($query) => $query->where('name', 'like', "%{$q}%"))
            ->withCount(['clients', 'trainingAssignments'])
            ->orderBy('name')
            ->paginate($perPage);

        return response()->json([
            'ok' => true,
            'data' => $groups->through(fn (Group $group) => $this->payload($group)),
        ]);
    }

    public function store(Request $request)
    {
        $coachId = $request->user()->id;
        $data = $this->validatedData($request, $coachId);

        $group = Group::create([
            ...$data,
            'coach_id' => $coachId,
            'is_active' => $data['is_active'] ?? true,
        ]);

        $group->loadCount(['clients', 'trainingAssignments']);

        return response()->json([
            'ok' => true,
            'message' => 'Grupo creado correctamente.',
            'data' => $this->payload($group),
        ], 201);
    }

    public function show(Request $request, Group $group)
    {
        $this->authorizeGroup($request, $group);

        $group->loadCount(['clients', 'trainingAssignments']);

        return response()->json([
            'ok' => true,
            'data' => $this->payload($group, true, $request->user()->id),
        ]);
    }

    public function update(Request $request, Group $group)
    {
        $this->authorizeGroup($request, $group);

        $group->update($this->validatedData($request, $request->user()->id, $group->id));
        $group->refresh()->loadCount(['clients', 'trainingAssignments']);

        return response()->json([
            'ok' => true,
            'message' => 'Grupo actualizado correctamente.',
            'data' => $this->payload($group),
        ]);
    }

    public function attachClient(Request $request, Group $group)
    {
        $this->authorizeGroup($request, $group);

        $data = $request->validate([
            'client_id' => [
                'required',
                'integer',
                Rule::exists('clients', 'id')->where(fn ($query) => $query->where('coach_id', $request->user()->id)),
            ],
        ]);

        $group->clients()->syncWithoutDetaching([(int) $data['client_id']]);
        $group->refresh()->loadCount(['clients', 'trainingAssignments']);

        return response()->json([
            'ok' => true,
            'message' => 'Atleta agregado al grupo.',
            'data' => $this->payload($group, true, $request->user()->id),
        ]);
    }

    public function detachClient(Request $request, Group $group, Client $client)
    {
        $this->authorizeGroup($request, $group);
        abort_unless((int) $client->coach_id === (int) $request->user()->id, 403);

        $group->clients()->detach($client->id);
        $group->refresh()->loadCount(['clients', 'trainingAssignments']);

        return response()->json([
            'ok' => true,
            'message' => 'Atleta removido del grupo.',
            'data' => $this->payload($group, true, $request->user()->id),
        ]);
    }

    private function validatedData(Request $request, int $coachId, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('groups', 'name')
                    ->where(fn ($query) => $query->where('coach_id', $coachId))
                    ->ignore($ignoreId),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    private function authorizeGroup(Request $request, Group $group): void
    {
        abort_unless((int) $group->coach_id === (int) $request->user()->id, 403);
    }

    private function payload(Group $group, bool $includeClients = false, ?int $coachId = null): array
    {
        $payload = [
            'id' => $group->id,
            'name' => $group->name,
            'description' => $group->description,
            'is_active' => (bool) $group->is_active,
            'clients_count' => (int) ($group->clients_count ?? 0),
            'training_assignments_count' => (int) ($group->training_assignments_count ?? 0),
            'created_at' => optional($group->created_at)->toISOString(),
        ];

        if ($includeClients) {
            $group->loadMissing(['clients' => fn ($query) => $query->orderBy('first_name')]);
            $payload['clients'] = $group->clients->map(fn ($client) => [
                'id' => $client->id,
                'full_name' => $client->full_name,
                'email' => $client->email,
            ])->values();

            $assignedIds = $group->clients->pluck('id')->all();
            $payload['available_clients'] = Client::query()
                ->where('coach_id', $coachId ?? $group->coach_id)
                ->where('is_active', true)
                ->when(!empty($assignedIds), fn ($query) => $query->whereNotIn('id', $assignedIds))
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name', 'email'])
                ->map(fn ($client) => [
                    'id' => $client->id,
                    'full_name' => $client->full_name,
                    'email' => $client->email,
                ])
                ->values();
        }

        return $payload;
    }
}
