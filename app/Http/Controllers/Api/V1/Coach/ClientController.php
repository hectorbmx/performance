<?php

namespace App\Http\Controllers\Api\V1\Coach;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\TrainingAssignment;
use App\Models\UserApp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $coachId = $request->user()->id;
        $q = trim((string) $request->query('q', ''));
        $perPage = min((int) $request->query('per_page', 15), 50);

        $clients = Client::query()
            ->where('coach_id', $coachId)
            ->with(['activeMembership', 'userApp:id,client_id,email,activated_at,is_active'])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('first_name', 'like', "%{$q}%")
                        ->orWhere('last_name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%")
                        ->orWhere('phone', 'like', "%{$q}%");
                });
            })
            ->orderBy('first_name')
            ->paginate($perPage);

        return response()->json([
            'ok' => true,
            'data' => $clients->through(fn (Client $client) => $this->clientPayload($client)),
        ]);
    }

    public function store(Request $request)
    {
        $coachId = $request->user()->id;
        $data = $this->validatedClientData($request);

        if (!empty($data['email'])) {
            $this->validateClientEmailIsAvailable($coachId, $data['email']);
        }

        $activationCode = null;

        $client = DB::transaction(function () use ($coachId, $data, &$activationCode) {
            $client = Client::create([
                'coach_id' => $coachId,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'] ?? null,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            if (!empty($data['email'])) {
                $activationCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

                UserApp::create([
                    'client_id' => $client->id,
                    'email' => $client->email,
                    'password' => null,
                    'is_active' => (bool) $client->is_active,
                    'activation_code' => Hash::make($activationCode),
                    'activation_expires_at' => now()->addDays(7),
                    'activated_at' => null,
                ]);
            }

            return $client;
        });

        $client->load(['activeMembership', 'userApp:id,client_id,email,activated_at,is_active']);

        return response()->json([
            'ok' => true,
            'message' => 'Cliente creado correctamente.',
            'data' => $this->clientPayload($client),
            'activation_code' => $activationCode,
        ], 201);
    }

    public function show(Request $request, Client $client)
    {
        $this->authorizeClient($request, $client);

        $client->load([
            'activeMembership',
            'latestMembership',
            'userApp:id,client_id,email,activated_at,is_active',
            'healthProfile',
        ]);

        return response()->json([
            'ok' => true,
            'data' => $this->clientPayload($client, true),
        ]);
    }

    public function update(Request $request, Client $client)
    {
        $this->authorizeClient($request, $client);

        $coachId = $request->user()->id;
        $data = $this->validatedClientData($request, $client->id);

        if (!empty($data['email']) && $data['email'] !== $client->email) {
            $this->validateClientEmailIsAvailable($coachId, $data['email'], $client->id);
        }

        DB::transaction(function () use ($client, $data) {
            $client->update([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'] ?? null,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'is_active' => $data['is_active'] ?? false,
            ]);

            if ($client->userApp) {
                $client->userApp->update([
                    'email' => $client->email ?: $client->userApp->email,
                    'is_active' => (bool) $client->is_active,
                ]);
            }
        });

        $client->refresh()->load(['activeMembership', 'userApp:id,client_id,email,activated_at,is_active']);

        return response()->json([
            'ok' => true,
            'message' => 'Cliente actualizado correctamente.',
            'data' => $this->clientPayload($client),
        ]);
    }

    public function destroy(Request $request, Client $client)
    {
        $this->authorizeClient($request, $client);

        $client->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Cliente eliminado correctamente.',
        ]);
    }

    public function trainings(Request $request, Client $client)
    {
        $this->authorizeClient($request, $client);

        $assignments = TrainingAssignment::query()
            ->where('client_id', $client->id)
            ->whereHas('trainingSession', fn ($query) => $query->where('coach_id', $request->user()->id))
            ->with(['trainingSession' => fn ($query) => $query->withCount('sections')])
            ->orderByDesc('scheduled_for')
            ->orderByDesc('id')
            ->paginate(min((int) $request->query('per_page', 25), 50));

        return response()->json([
            'ok' => true,
            'data' => $assignments->through(fn (TrainingAssignment $assignment) => [
                'assignment_id' => $assignment->id,
                'status' => $assignment->status,
                'status_label' => $this->trainingStatusLabel($assignment->status),
                'scheduled_for' => optional($assignment->scheduled_for)->toDateString(),
                'training' => $assignment->trainingSession ? [
                    'id' => $assignment->trainingSession->id,
                    'title' => $assignment->trainingSession->title,
                    'duration_minutes' => $assignment->trainingSession->duration_minutes,
                    'level' => $assignment->trainingSession->level,
                    'goal' => $assignment->trainingSession->goal,
                    'type' => $assignment->trainingSession->type,
                    'sections_count' => $assignment->trainingSession->sections_count,
                ] : null,
            ]),
        ]);
    }

    private function validatedClientData(Request $request): array
    {
        return $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    private function validateClientEmailIsAvailable(int $coachId, string $email, ?int $exceptClientId = null): void
    {
        validator(
            ['email' => $email],
            [
                'email' => [
                    Rule::unique('clients', 'email')
                        ->where(fn ($query) => $query->where('coach_id', $coachId))
                        ->ignore($exceptClientId),
                    Rule::unique('user_apps', 'email'),
                ],
            ],
            [
                'email.unique' => 'Este email ya esta registrado.',
            ]
        )->validate();
    }

    private function authorizeClient(Request $request, Client $client): void
    {
        abort_unless((int) $client->coach_id === (int) $request->user()->id, 403);
    }

    private function clientPayload(Client $client, bool $includeDetails = false): array
    {
        $payload = [
            'id' => $client->id,
            'first_name' => $client->first_name,
            'last_name' => $client->last_name,
            'full_name' => $client->full_name,
            'email' => $client->email,
            'phone' => $client->phone,
            'is_active' => (bool) $client->is_active,
            'app_account' => $client->userApp ? [
                'id' => $client->userApp->id,
                'email' => $client->userApp->email,
                'is_active' => (bool) $client->userApp->is_active,
                'activated_at' => optional($client->userApp->activated_at)->toISOString(),
            ] : null,
            'active_membership' => $client->activeMembership ? [
                'id' => $client->activeMembership->id,
                'plan_name' => $client->activeMembership->plan_name_snapshot,
                'status' => $client->activeMembership->status,
                'billing_status' => $client->activeMembership->billing_status,
                'starts_at' => optional($client->activeMembership->starts_at)->toDateString(),
                'ends_at' => optional($client->activeMembership->ends_at)->toDateString(),
            ] : null,
        ];

        if ($includeDetails) {
            $payload['health_profile'] = $client->healthProfile;
        }

        return $payload;
    }

    private function trainingStatusLabel(string $status): string
    {
        return match ($status) {
            'scheduled' => 'Programado',
            'in_progress' => 'En progreso',
            'completed' => 'Completado',
            'skipped' => 'Saltado',
            'cancelled' => 'Cancelado',
            default => $status,
        };
    }
}
