<?php

namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientHealthProfile;
use App\Models\TrainingMetric;
use App\Models\UserApp;
use App\Support\MexicoStates;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class ClientController extends Controller
{
    public function index()
    {
        $coachId = auth()->id();
        $q = trim(request('q', ''));

        $clients = Client::query()
            ->where('coach_id', $coachId)
            ->with('activeMembership')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('first_name', 'like', "%{$q}%")
                        ->orWhere('last_name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%")
                        ->orWhere('phone', 'like', "%{$q}%");
                });
            })
            ->orderBy('first_name')
            ->paginate(10)
            ->appends(['q' => $q]);

        return view('coach.clients.index', compact('clients', 'q'));
    }

    public function create()
    {
        $mexicoStates = MexicoStates::all();

        return view('coach.clients.create', compact('mexicoStates'));
    }

    public function store(Request $request)
    {
        $coachId = auth()->id();

        $data = $request->validate($this->clientRules($coachId), $this->clientMessages());

        if (!empty($data['email']) && UserApp::where('email', $data['email'])->exists()) {
            return back()
                ->withErrors(['email' => 'Este email ya esta registrado para la App.'])
                ->withInput();
        }

        $healthData = [
            'state' => $data['state'] ?? null,
            'city' => $data['city'] ?? null,
        ];

        unset($data['state'], $data['city']);

        $data['coach_id'] = $coachId;
        $data['is_active'] = $data['is_active'] ?? true;

        $client = null;
        $setupPasswordUrl = null;

        DB::transaction(function () use ($data, $healthData, &$client, &$setupPasswordUrl) {
            $client = Client::create($data);

            if (!empty($healthData['state']) || !empty($healthData['city'])) {
                ClientHealthProfile::create([
                    'client_id' => $client->id,
                    'state' => $healthData['state'],
                    'city' => $healthData['city'],
                ]);
            }

            if (!empty($data['email'])) {
                $userApp = UserApp::create([
                    'client_id' => $client->id,
                    'email' => $client->email,
                    'password' => null,
                    'is_active' => (bool) $client->is_active,
                    'activation_code' => null,
                    'activation_expires_at' => null,
                    'activated_at' => null,
                ]);

                $setupPasswordUrl = $this->createPasswordSetupUrl($userApp);
            }
        });

        return redirect()
            ->route('coach.clients.index')
            ->with('success', !empty($data['email'])
                ? 'Atleta creado correctamente. Comparte el enlace para que configure su contrasena.'
                : 'Atleta creado correctamente.')
            ->with('setup_password_url', $setupPasswordUrl);
    }

    public function show(string $id)
    {
        //
    }

    public function edit(Client $client)
    {
        abort_unless($client->coach_id === auth()->id(), 403);

        $client->load([
            'healthProfile',
            'memberships' => fn ($q) => $q->latest('starts_at')
                ->with([
                    'coachClientPlan',
                    'payments' => fn ($p) => $p->latest('payment_date'),
                ]),
            'metricRecords' => fn ($q) => $q->latest('recorded_at')->latest('id')->with('trainingMetric')->limit(15),
        ]);

        $metrics = TrainingMetric::query()
            ->availableForCoach(auth()->id())
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'unit', 'type']);

        $bodyRecords = $client->bodyRecords()
            ->latest('recorded_at')
            ->latest('id')
            ->limit(12)
            ->get();

        $latestBodyRecord = $bodyRecords->first();
        $previousBodyRecord = $bodyRecords->skip(1)->first();

        $weightSummary = [
            'current' => $latestBodyRecord,
            'previous' => $previousBodyRecord,
            'change_kg' => $latestBodyRecord && $previousBodyRecord
                ? round((float) $latestBodyRecord->weight_kg - (float) $previousBodyRecord->weight_kg, 2)
                : null,
            'records_count' => $bodyRecords->count(),
        ];

        $progressMetricRecords = $client->metricRecords()
            ->with('trainingMetric')
            ->latest('recorded_at')
            ->latest('id')
            ->limit(80)
            ->get();

        $latestProgressMetricRecord = $progressMetricRecords->first();

        $metricSummaries = $progressMetricRecords
            ->groupBy('training_metric_id')
            ->map(function ($records) {
                $latestRecord = $records->first();
                $values = $records->pluck('value')->map(fn ($value) => (float) $value);

                return [
                    'metric' => $latestRecord?->trainingMetric,
                    'latest_record' => $latestRecord,
                    'max_value' => $values->max(),
                    'min_value' => $values->min(),
                    'records_count' => $records->count(),
                    'recent_records' => $records->take(5)->values(),
                ];
            })
            ->values();

        $mexicoStates = MexicoStates::all();

        return view('coach.clients.edit', compact(
            'client',
            'metrics',
            'mexicoStates',
            'bodyRecords',
            'weightSummary',
            'progressMetricRecords',
            'latestProgressMetricRecord',
            'metricSummaries'
        ));
    }

    public function update(Request $request, Client $client)
    {
        abort_unless($client->coach_id === auth()->id(), 403);

        $data = $request->validate(
            array_merge($this->clientRules(auth()->id(), $client->id), $this->healthProfileRules()),
            $this->clientMessages()
        );

        $healthData = [
            'state' => $data['state'] ?? null,
            'city' => $data['city'] ?? null,
            'zip_code' => $data['zip_code'] ?? null,
            'birth_date' => $data['birth_date'] ?? null,
            'gender' => $data['gender'] ?? null,
            'height_cm' => $data['height_cm'] ?? null,
        ];

        unset($data['state'], $data['city'], $data['zip_code'], $data['birth_date'], $data['gender'], $data['height_cm']);

        $data['is_active'] = $request->has('is_active');

        DB::transaction(function () use ($client, $data, $healthData) {
            $client->update($data);

            ClientHealthProfile::updateOrCreate(
                ['client_id' => $client->id],
                $healthData
            );
        });

        return redirect()
            ->route('coach.clients.edit', $client)
            ->with('success', 'Cliente actualizado correctamente.')
            ->with('active_client_tab', 'datos_generales');
    }

    public function destroy(Client $client)
    {
        abort_unless($client->coach_id === auth()->id(), 403);

        $client->delete();

        return redirect()
            ->route('coach.clients.index')
            ->with('success', 'Cliente eliminado correctamente.');
    }

    public function resendActivationCode(Client $client)
    {
        abort_unless($client->coach_id === auth()->id(), 403);

        $userApp = UserApp::where('client_id', $client->id)->first();

        if (!$userApp) {
            return back()
                ->withErrors(['activation_code' => 'Este cliente no tiene cuenta para la App (falta email).'])
                ->with('active_client_tab', 'datos_generales');
        }

        if (!is_null($userApp->password)) {
            return back()
                ->withErrors(['activation_code' => 'Este atleta ya configuro su contrasena.'])
                ->with('active_client_tab', 'datos_generales');
        }

        $userApp->update([
            'activation_code' => null,
            'activation_expires_at' => null,
        ]);

        return back()
            ->with('success', 'Enlace generado. Compartelo con el atleta para que configure su contrasena.')
            ->with('active_client_tab', 'datos_generales')
            ->with('setup_password_url', $this->createPasswordSetupUrl($userApp));
    }

    public function search(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json([
                'ok' => true,
                'data' => [],
            ]);
        }

        $coachId = auth()->id();

        $clients = Client::query()
            ->where('coach_id', $coachId)
            ->where('is_active', 1)
            ->where(function ($query) use ($q) {
                $query->where('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            })
            ->orderBy('first_name')
            ->limit(15)
            ->get(['id', 'first_name', 'last_name', 'email']);

        $data = $clients->map(function ($c) {
            $name = trim(($c->first_name ?? '') . ' ' . ($c->last_name ?? ''));
            $label = $name !== '' ? $name : ($c->email ?? ('Cliente #' . $c->id));

            return [
                'id' => (int) $c->id,
                'label' => $label,
                'email' => $c->email,
            ];
        })->values();

        return response()->json([
            'ok' => true,
            'data' => $data,
        ]);
    }

    private function clientRules(int $coachId, ?int $ignoreClientId = null): array
    {
        $emailRule = Rule::unique('clients', 'email')
            ->where(fn ($query) => $query->where('coach_id', $coachId));

        if ($ignoreClientId) {
            $emailRule->ignore($ignoreClientId);
        }

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email:rfc', 'max:150', $emailRule],
            'phone' => ['nullable', 'regex:/^(?:\+52\s?)?(?:\d{10}|(?:\d{2}\s?){5})$/', 'max:30'],
            'state' => ['nullable', Rule::in(MexicoStates::all())],
            'city' => ['nullable', 'string', 'max:120'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    private function healthProfileRules(): array
    {
        return [
            'zip_code' => ['nullable', 'string', 'max:20'],
            'birth_date' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', 'max:30'],
            'height_cm' => ['nullable', 'integer', 'min:50', 'max:260'],
        ];
    }

    private function clientMessages(): array
    {
        return [
            'email.email' => 'Ingresa un correo valido.',
            'email.unique' => 'Este correo ya existe en tus clientes.',
            'phone.regex' => 'Ingresa un celular mexicano valido de 10 digitos.',
            'state.in' => 'Selecciona un estado valido.',
        ];
    }

    private function createPasswordSetupUrl(UserApp $userApp): string
    {
        $token = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $userApp->email],
            [
                'token' => Hash::make($token),
                'created_at' => now(),
            ]
        );

        return route('app.password-setup.show', [
            'token' => $token,
            'email' => $userApp->email,
        ]);
    }
}
