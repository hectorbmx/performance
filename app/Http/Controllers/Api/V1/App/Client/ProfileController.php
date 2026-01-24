<?php

namespace App\Http\Controllers\Api\V1\App\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Models\Client;
use App\Models\UserApp;

use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user(); // Usuario autenticado (Sanctum)

        // Ajusta esto según tu relación real:
        // En tus mensajes anteriores usas: User->usuarioApp->empleado_id
        // En Coach SaaS normalmente: User -> userApp -> client
        $userApp = $user->userApp ?? $user->usuarioApp ?? null;

        if (!$userApp || !$userApp->client) {
            return response()->json([
                'ok' => false,
                'message' => 'Perfil no disponible para este usuario.',
            ], 404);
        }

        $client = $userApp->client;

        $avatarUrl = $client->avatar
            ? url(Storage::disk('public')->url($client->avatar))
            : null;

        return response()->json([
            'ok' => true,
            'profile' => [
                'client' => [
                    'id' => (int) $client->id,
                    'first_name' => $client->first_name,
                    'last_name' => $client->last_name,
                    'full_name' => trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? '')),
                    'email' => $client->email,
                    'phone' => $client->phone,
                    'avatar_url' => $avatarUrl,
                    'is_active' => (bool) $client->is_active,
                    'created_at' => optional($client->created_at)->toISOString(),
                ],
                'account' => [
                    'user_app_id' => (int) $userApp->id,
                    'last_login_at' => $userApp->last_login_at,
                ],
                // placeholders por si luego conectas stats reales
                'stats' => [
                    'workouts' => 0,
                    'day_streak' => 0,
                    'volume' => 0,
                ],
            ],
        ]);
    }
    public function storeAvatar(Request $request)
        {
            $user = $request->user();
            // $userApp = $user->userApp ?? $user->usuarioApp ?? null;
                $userApp = UserApp::where('email', $user->email)->first();


             if (!$userApp || !$userApp->client_id) {
                    return response()->json([
                        'ok' => false,
                        'message' => 'No existe contexto de usuario app.',
                    ], 404);
                }

            // $client = $userApp->client ?? null;
            $client = Client::find($userApp->client_id);

            // if (!$client && isset($userApp->client_id)) {
            //     $client = Client::find($userApp->client_id);
            // }

            if (!$client) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Cliente no encontrado.',
                ], 404);
            }

            $data = $request->validate([
                'avatar' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'], // 4MB
            ]);

            try {
                $result = DB::transaction(function () use ($client, $data) {
                    $disk = Storage::disk('public');

                    // (opcional pero recomendado) borrar avatar anterior si existe
                    if ($client->avatar && $disk->exists($client->avatar)) {
                        $disk->delete($client->avatar);
                    }

                    $file = $data['avatar'];

                    // Nombre único y limpio
                    $ext = $file->getClientOriginalExtension() ?: 'jpg';
                    $filename = 'avatar_' . $client->id . '_' . Str::uuid() . '.' . $ext;

                    // Guardar en: storage/app/public/clients/avatars/...
                    $path = $file->storeAs('clients/avatars', $filename, 'public');

                    $client->avatar = $path;
                    $client->save();

                    return [
                        'avatar_path' => $path,
                        'avatar_url'  => url($disk->url($path)),
                    ];
                });

                return response()->json([
                    'ok' => true,
                    'message' => 'Avatar actualizado.',
                    'avatar' => $result,
                ]);
            } catch (\Throwable $e) {
                report($e);

                return response()->json([
                    'ok' => false,
                    'message' => 'No se pudo actualizar el avatar.',
                ], 500);
            }
        }

}
