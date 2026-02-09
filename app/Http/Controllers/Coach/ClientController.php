<?php

namespace App\Http\Controllers\Coach;

use App\Models\Client;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\UserApp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ClientController extends Controller


{
    /**
     * Display a listing of the resource.
     */
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
            ->appends(['q' => $q]); // mantiene el querystring en links

        return view('coach.clients.index', compact('clients', 'q'));
    }



    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
        return view('coach.clients.create');
    }

    /**
     * Store a newly created resource in storage.
     */
public function store(Request $request)
{
    $coachId = auth()->id();

    $data = $request->validate([
        'first_name' => ['required','string','max:100'],
        'last_name'  => ['nullable','string','max:100'],
        'email'      => ['nullable','email','max:150'],
        'phone'      => ['nullable','string','max:30'],
        'is_active'  => ['sometimes','boolean'],
    ]);

    // Evitar duplicado por coach + email (si viene email)
    if (!empty($data['email'])) {
        $exists = Client::where('coach_id', $coachId)
            ->where('email', $data['email'])
            ->exists();

        if ($exists) {
            return back()
                ->withErrors(['email' => 'Este email ya existe en tus clientes.'])
                ->withInput();
        }

        // Evitar duplicado global en users_app (email único global)
        $existsUserApp = UserApp::where('email', $data['email'])->exists();
        if ($existsUserApp) {
            return back()
                ->withErrors(['email' => 'Este email ya está registrado para la App.'])
                ->withInput();
        }
    }

    $data['coach_id'] = $coachId;
    $data['is_active'] = $data['is_active'] ?? true;

    $activationCode = null;
    $client = null;

  DB::transaction(function () use ($data, &$activationCode, &$client) {

    $client = Client::create($data);

    if (!empty($data['email'])) {
        $activationCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        UserApp::create([
            'client_id'             => $client->id,
            'email'                 => $client->email,
            'password'              => null,
            'is_active'             => (bool) $client->is_active,
            // 'activation_code'       => $activationCode,
            'activation_code' => \Illuminate\Support\Facades\Hash::make($activationCode),

            'activation_expires_at' => now()->addDays(7),
            'activated_at'          => null,
        ]);
    }
});

    return redirect()
        ->route('coach.clients.index')
        ->with('success', 'Cliente creado correctamente.')
        ->with('activation_code', $activationCode); // puede ser null si no hubo email
}

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
public function edit(Client $client)
{
    abort_unless($client->coach_id === auth()->id(), 403);

    $client->load([
        'healthProfile',
        'memberships' => fn($q) => $q->latest('starts_at')
            ->with(['payments' => fn($p) => $p->latest('payment_date')]),
        'metricRecords' => fn($q) => $q->latest('recorded_at')->latest('id')->with('trainingMetric')->limit(15),
    ]);

    $metrics = \App\Models\TrainingMetric::query()
        ->where('coach_id', auth()->id())
        ->where('is_active', 1)
        ->orderBy('name')
        ->get(['id','code','name','unit','type']);

    return view('coach.clients.edit', compact('client','metrics'));
}


    /**
     * Update the specified resource in storage.
     */
  public function update(Request $request, Client $client)
{
    abort_unless($client->coach_id === auth()->id(), 403);

    $data = $request->validate([
        'first_name' => ['required','string','max:100'],
        'last_name'  => ['nullable','string','max:100'],
        'email'      => ['nullable','email','max:150'],
        'phone'      => ['nullable','string','max:30'],
        'is_active'  => ['sometimes','boolean'],
    ]);

    $coachId = auth()->id();

    // Evitar duplicado por coach + email (si viene email)
    if (!empty($data['email'])) {
        $exists = Client::where('coach_id', $coachId)
            ->where('email', $data['email'])
            ->where('id', '!=', $client->id)
            ->exists();

        if ($exists) {
            return back()
                ->withErrors(['email' => 'Este email ya existe en tus clientes.'])
                ->withInput();
        }
    }

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
    // Seguridad: asegurar que el coach solo afecte sus clientes
    abort_unless($client->coach_id === auth()->id(), 403);

    // Debe existir cuenta app
    $userApp = UserApp::where('client_id', $client->id)->first();

    if (!$userApp) {
        return back()->withErrors(['activation_code' => 'Este cliente no tiene cuenta para la App (falta email).']);
    }

    // Si ya está activado (tiene password), no reenviar
    if (!is_null($userApp->password)) {
        return back()->withErrors(['activation_code' => 'Este cliente ya activó su cuenta.']);
    }

    // Re-generar código
    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

    $userApp->update([
        'activation_code'       => Hash::make($code),
        'activation_expires_at' => now()->addDays(7),
    ]);

    // Por ahora: devolvemos el código para que el coach lo vea (como tu alert actual)
    return back()
        ->with('success', 'Código de activación regenerado.')
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

    $clients = \App\Models\Client::query()
        ->where('coach_id', $coachId)
        ->where('is_active', 1)
        ->where(function ($query) use ($q) {
            $query->where('first_name', 'like', "%{$q}%")
                  ->orWhere('last_name', 'like', "%{$q}%")
                  ->orWhere('email', 'like', "%{$q}%");
        })
        ->orderBy('first_name')
        ->limit(15)
        ->get(['id','first_name','last_name','email']);

    $data = $clients->map(function ($c) {
        $name = trim(($c->first_name ?? '').' '.($c->last_name ?? ''));
        $label = $name !== '' ? $name : ($c->email ?? ('Cliente #'.$c->id));

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

}
