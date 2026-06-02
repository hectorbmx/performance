<?php

namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientHealthProfile;
use App\Models\UserApp;
use App\Support\MexicoStates;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

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

        $activationCode = null;
        $client = null;

        DB::transaction(function () use ($data, $healthData, &$activationCode, &$client) {
            $client = Client::create($data);

            if (!empty($healthData['state']) || !empty($healthData['city'])) {
                ClientHealthProfile::create([
                    'client_id' => $client->id,
                    'state' => $healthData['state'],
                    'city' => $healthData['city'],
                ]);
            }

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
        });

        return redirect()
            ->route('coach.clients.index')
            ->with('success', 'Cliente creado correctamente.')
            ->with('activation_code', $activationCode);
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
                ->with(['payments' => fn ($p) => $p->latest('payment_date')]),
            'metricRecords' => fn ($q) => $q->latest('recorded_at')->latest('id')->with('trainingMetric')->limit(15),
        ]);

        $metrics = \App\Models\TrainingMetric::query()
            ->where(function ($query) {
                $query->whereNull('coach_id')
                    ->orWhere('coach_id', auth()->id());
            })
            ->where('is_active', 1)
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'unit', 'type']);

        $mexicoStates = MexicoStates::all();

        return view('coach.clients.edit', compact('client', 'metrics', 'mexicoStates'));
    }

    public function update(Request $request, Client $client)
    {
        abort_unless($client->coach_id === auth()->id(), 403);

        $data = $request->validate($this->clientRules(auth()->id(), $client->id), $this->clientMessages());

        unset($data['state'], $data['city']);

        $data['is_active'] = $request->has('is_active');

        $client->update($data);

        return redirect()
            ->route('coach.clients.index')
            ->with('success', 'Cliente actualizado correctamente.');
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
            return back()->withErrors(['activation_code' => 'Este cliente no tiene cuenta para la App (falta email).']);
        }

        if (!is_null($userApp->password)) {
            return back()->withErrors(['activation_code' => 'Este cliente ya activo su cuenta.']);
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $userApp->update([
            'activation_code' => Hash::make($code),
            'activation_expires_at' => now()->addDays(7),
        ]);

        return back()
            ->with('success', 'Codigo de activacion regenerado.')
            ->with('activation_code', $code);
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

    private function clientMessages(): array
    {
        return [
            'email.email' => 'Ingresa un correo valido.',
            'email.unique' => 'Este correo ya existe en tus clientes.',
            'phone.regex' => 'Ingresa un celular mexicano valido de 10 digitos.',
            'state.in' => 'Selecciona un estado valido.',
        ];
    }
}
