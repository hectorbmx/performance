<?php

namespace App\Http\Controllers\Api\V1\App;

use App\Http\Controllers\Controller;
use App\Models\UserApp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\CoachTrainingMetric;
use App\Models\ClientMetricRecord;
use App\Models\TrainingMetric;
use Illuminate\Support\Facades\Storage;


class AuthController extends Controller
{
    public function activate(Request $request)
    {
        $data = $request->validate([
            'email'           => ['required','email'],
            'activation_code' => ['required','digits:6'],
            'password'        => ['required','string','min:8','confirmed'],
        ]);

        $userApp = UserApp::where('email', $data['email'])
            ->where('is_active', 1)
            ->first();

        if (!$userApp) {
            return response()->json([
                'ok' => false,
                'message' => 'Cuenta no encontrada o inactiva.'
            ], 404);
        }

        // Ya activada
        if (!is_null($userApp->password)) {
            return response()->json([
                'ok' => false,
                'message' => 'Esta cuenta ya fue activada.'
            ], 422);
        }

        // Código incorrecto
        // if ($userApp->activation_code !== $data['activation_code']) {
        if (!Hash::check($data['activation_code'], $userApp->activation_code)) {
    
            return response()->json([
                'ok' => false,
                'message' => 'Código de activación incorrecto.'
            ], 422);
        }

        // Código expirado
        if ($userApp->activation_expires_at && now()->greaterThan($userApp->activation_expires_at)) {
            return response()->json([
                'ok' => false,
                'message' => 'El código de activación ha expirado.'
            ], 422);
        }

        // Activar cuenta
        $userApp->update([
            'password'              => Hash::make($data['password']),
            'activated_at'          => now(),
            'activation_code'       => null,
            'activation_expires_at' => null,
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Cuenta activada correctamente. Ya puedes iniciar sesión.'
        ]);
    }
public function login(Request $request)
{
    $data = $request->validate([
        'email'    => ['required','email'],
        'password' => ['required','string'],
    ]);

    $userApp = UserApp::with('client')
        ->where('email', $data['email'])
        ->first();

    if (!$userApp || !$userApp->is_active) {
        return response()->json([
            'ok' => false,
            'message' => 'Credenciales inválidas.'
        ], 422);
    }

    // Si no está activada (password null)
    if (is_null($userApp->password)) {
        return response()->json([
            'ok' => false,
            'message' => 'Cuenta pendiente de activación.'
        ], 422);
    }

    if (!Hash::check($data['password'], $userApp->password)) {
        return response()->json([
            'ok' => false,
            'message' => 'Credenciales inválidas.'
        ], 422);
    }

    // (Opcional) registrar last_login_at
    $userApp->forceFill(['last_login_at' => now()])->save();

    // Crear token Sanctum
    $token = $userApp->createToken('app')->plainTextToken;

  return response()->json([
    'ok' => true,
    'token' => $token,
    'context' => [
        'user_app_id' => $userApp->id,
        'client_id'   => $userApp->client_id,
        'coach_id'    => $userApp->client->coach_id,
    ],
    'user' => [
        'id' => $userApp->id,
        'email' => $userApp->email,
    ],
    'client' => [
        'id' => $userApp->client->id,
        'first_name' => $userApp->client->first_name,
        'last_name' => $userApp->client->last_name,
    ],
]);

}

public function logout(Request $request)
{
    /** @var \App\Models\UserApp $user */
    $user = $request->user();

    // Revoca solo el token actual
    $user->currentAccessToken()->delete();

    return response()->json([
        'ok' => true,
        'message' => 'Sesión cerrada correctamente.'
    ]);
}
public function me(Request $request)
{
    $auth = $request->user();


    if (!$auth) {
            return response()->json([
                'ok' => false,
                'message' => 'No autenticado.'
            ], 401);
        }


    $userApp = UserApp::query()
        ->select(['id','email','client_id','is_active'])
        ->with(['client:id,coach_id,first_name,last_name,is_active,avatar'])
        // ->findOrFail($auth->id);
        ->where('email', $auth->email)->firstOrFail();


    return response()->json([
        'ok' => true,
        'user' => [
            'id' => $userApp->id,
            'email' => $userApp->email,
            'client_id' => $userApp->client_id,
            'is_active' => (bool) $userApp->is_active,
        ],
        'client' => $userApp->client ? [
            'id' => $userApp->client->id,
            'first_name' => $userApp->client->first_name,
            'last_name' => $userApp->client->last_name,
            'coach_id' => $userApp->client->coach_id,
            'avatar_url' => $userApp->client->avatar ? url(Storage::disk('public')->url($userApp->client->avatar)) : null,
            'is_active' => (bool) $userApp->client->is_active,
        ] : null,
    ]);
}


//reenvio de condigo de activacion
public function resendActivationCode(Request $request)
{

        // dd('HIT resendActivationCode', $client->id);

    $data = $request->validate([
        'email' => ['required', 'email'],
    ]);

    $userApp = UserApp::where('email', $data['email'])->first();

    if (!$userApp) {
        return response()->json([
            'ok' => false,
            'message' => 'Cuenta no encontrada.'
        ], 404);
    }

    if (!$userApp->is_active) {
        return response()->json([
            'ok' => false,
            'message' => 'Cuenta inactiva.'
        ], 403);
    }

    // Si ya está activada (ya tiene password)
    if (!is_null($userApp->password)) {
        return response()->json([
            'ok' => false,
            'message' => 'Esta cuenta ya fue activada.'
        ], 422);
    }

    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

    $userApp->update([
        'activation_code'       => Hash::make($code),
        'activation_expires_at' => now()->addDays(7), // o 30 mins si quieres más estricto
    ]);

    // En el siguiente paso lo mandamos por correo/whatsapp.
    // Por ahora solo confirmamos.
    return response()->json([
        'ok' => true,
        'message' => 'Código reenviado.',
        // OJO: en producción NO regreses el código.
        // 'activation_code' => $code,
    ]);
}
public function meProfile(Request $request)
{
    $auth = $request->user();

    if (!$auth) {
        return response()->json([
            'ok' => false,
            'message' => 'No autenticado.'
        ], 401);
    }

    // UserApp autenticado (Sanctum)
    $userApp = UserApp::query()
        ->select(['id','email','client_id','is_active'])
        ->with(['client:id,coach_id,first_name,last_name,email,phone,is_active'])
        ->findOrFail($auth->id);

    if (!$userApp->client_id || !$userApp->client) {
        return response()->json([
            'ok' => false,
            'message' => 'Cliente no asociado a este usuario.'
        ], 422);
    }

    $client = $userApp->client;

    // 1) Perfil salud (crear si no existe para evitar null)
    $health = $client->healthProfile()->firstOrCreate([]);

    // 2) Último peso (puede ser null)
    $latestBody = $client->latestBodyRecord()->first();

    // 3) Métricas habilitadas por el coach (User)
    $configs = CoachTrainingMetric::query()
        ->where('coach_id', $client->coach_id)
        ->with(['metric:id,code,name,unit,type,is_active'])
        ->ordered()
        ->get();

    // 4) Último valor por métrica para este cliente (en 1 query)
    $lastByMetric = ClientMetricRecord::query()
        ->where('client_id', $client->id)
        ->orderByDesc('recorded_at')
        ->orderByDesc('id')
        ->get()
        ->groupBy('training_metric_id')
        ->map(fn ($rows) => $rows->first());

    $metrics = $configs->map(function ($cfg) use ($lastByMetric) {
        $metric = $cfg->metric;
        $last = $metric ? ($lastByMetric[$metric->id] ?? null) : null;

        return [
            'id' => $metric?->id,
            'code' => $metric?->code,
            'name' => $metric?->name,
            'unit' => $metric?->unit,
            'type' => $metric?->type,
            'is_required' => (bool) $cfg->is_required,
            'sort_order' => (int) $cfg->sort_order,
            'last' => $last ? [
                'value' => (float) $last->value,
                'recorded_at' => optional($last->recorded_at)->toISOString(),
                'source' => $last->source,
                'notes' => $last->notes,
            ] : null,
        ];
    })->values();

    return response()->json([
        'ok' => true,
        'user' => [
            'id' => $userApp->id,
            'email' => $userApp->email,
            'client_id' => $userApp->client_id,
            'is_active' => (bool) $userApp->is_active,
        ],
        'client' => [
            'id' => $client->id,
            'first_name' => $client->first_name,
            'last_name' => $client->last_name,
            'email' => $client->email,
            'phone' => $client->phone,
            'coach_id' => $client->coach_id,
            'is_active' => (bool) $client->is_active,
        ],
        'health_profile' => [
            'state' => $health->state,
            'city' => $health->city,
            'zip_code' => $health->zip_code,
            'birth_date' => optional($health->birth_date)->toDateString(),
            'gender' => $health->gender,
            'height_cm' => $health->height_cm,
        ],
        'body' => [
            'latest' => $latestBody ? [
                'weight_kg' => $latestBody->weight_kg !== null ? (float) $latestBody->weight_kg : null,
                'recorded_at' => optional($latestBody->recorded_at)->toISOString(),
                'source' => $latestBody->source,
                'notes' => $latestBody->notes,
            ] : null,
        ],
        'metrics' => $metrics,
    ]);
}
public function updateHealthProfile(Request $request)
{
    $auth = $request->user();

    if (!$auth) {
        return response()->json([
            'ok' => false,
            'message' => 'No autenticado.'
        ], 401);
    }

    $data = $request->validate([
        'state'      => ['nullable','string','max:100'],
        'city'       => ['nullable','string','max:120'],
        'zip_code'   => ['nullable','string','max:20'],
        'birth_date' => ['nullable','date'],
        'gender'     => ['nullable','string','max:30'],
        'height_cm'  => ['nullable','integer','min:50','max:260'],
    ]);

    $userApp = UserApp::query()
        ->with('client')
        ->findOrFail($auth->id);

    if (!$userApp->client) {
        return response()->json([
            'ok' => false,
            'message' => 'Cliente no asociado a este usuario.'
        ], 422);
    }

    $health = $userApp->client->healthProfile()->firstOrCreate([]);
    $health->fill($data);
    $health->save();

    return response()->json([
        'ok' => true,
        'health_profile' => [
            'state' => $health->state,
            'city' => $health->city,
            'zip_code' => $health->zip_code,
            'birth_date' => optional($health->birth_date)->toDateString(),
            'gender' => $health->gender,
            'height_cm' => $health->height_cm,
        ],
    ]);
}
public function storeBodyRecord(Request $request)
{
    $auth = $request->user();

    if (!$auth) {
        return response()->json([
            'ok' => false,
            'message' => 'No autenticado.'
        ], 401);
    }

    $data = $request->validate([
        'weight_kg'   => ['required','numeric','min:20','max:500'],
        'recorded_at' => ['nullable','date'],
        'notes'       => ['nullable','string','max:255'],
    ]);

    $userApp = UserApp::query()
        ->with('client')
        ->findOrFail($auth->id);

    if (!$userApp->client) {
        return response()->json([
            'ok' => false,
            'message' => 'Cliente no asociado a este usuario.'
        ], 422);
    }

    $record = $userApp->client->bodyRecords()->create([
        'weight_kg'   => $data['weight_kg'],
        'recorded_at' => $data['recorded_at'] ?? now(),
        'source'      => 'manual',
        'notes'       => $data['notes'] ?? null,
    ]);

    return response()->json([
        'ok' => true,
        'body_record' => [
            'id' => $record->id,
            'weight_kg' => (float) $record->weight_kg,
            'recorded_at' => optional($record->recorded_at)->toISOString(),
            'source' => $record->source,
            'notes' => $record->notes,
        ],
    ], 201);
}
public function storeMetricRecord(Request $request)
{
    $auth = $request->user();

    if (!$auth) {
        return response()->json([
            'ok' => false,
            'message' => 'No autenticado.'
        ], 401);
    }

    $data = $request->validate([
        // Permitir enviar metric_id o metric_code (más cómodo para app)
        'training_metric_id' => ['nullable','integer','exists:training_metrics,id'],
        'metric_code'        => ['nullable','string','max:80'],

        'value'              => ['required','numeric','min:0','max:99999'],
        'recorded_at'        => ['nullable','date'],
        'notes'              => ['nullable','string','max:255'],
    ]);

    if (empty($data['training_metric_id']) && empty($data['metric_code'])) {
        return response()->json([
            'ok' => false,
            'message' => 'Debes enviar training_metric_id o metric_code.'
        ], 422);
    }

    $userApp = UserApp::query()
        ->with('client')
        ->findOrFail($auth->id);

    if (!$userApp->client) {
        return response()->json([
            'ok' => false,
            'message' => 'Cliente no asociado a este usuario.'
        ], 422);
    }

    $client = $userApp->client;

    // Resolver métrica
    $metric = null;

    if (!empty($data['training_metric_id'])) {
        $metric = TrainingMetric::query()->active()->find($data['training_metric_id']);
    } else {
        $metric = TrainingMetric::query()->active()->where('code', $data['metric_code'])->first();
    }

    if (!$metric) {
        return response()->json([
            'ok' => false,
            'message' => 'Métrica no encontrada o inactiva.'
        ], 404);
    }

    // Seguridad: validar que la métrica esté habilitada para el coach del cliente
    $enabled = CoachTrainingMetric::query()
        ->where('coach_id', $client->coach_id)
        ->where('training_metric_id', $metric->id)
        ->exists();

    if (!$enabled) {
        return response()->json([
            'ok' => false,
            'message' => 'Métrica no habilitada para este coach.'
        ], 403);
    }

    $record = $client->metricRecords()->create([
        'training_metric_id' => $metric->id,
        'value'              => $data['value'],
        'recorded_at'         => $data['recorded_at'] ?? now(),
        'source'             => 'manual',
        'notes'              => $data['notes'] ?? null,
    ]);

    return response()->json([
        'ok' => true,
        'metric_record' => [
            'id' => $record->id,
            'training_metric' => [
                'id' => $metric->id,
                'code' => $metric->code,
                'name' => $metric->name,
                'unit' => $metric->unit,
                'type' => $metric->type,
            ],
            'value' => (float) $record->value,
            'recorded_at' => optional($record->recorded_at)->toISOString(),
            'source' => $record->source,
            'notes' => $record->notes,
        ],
    ], 201);
}

}

