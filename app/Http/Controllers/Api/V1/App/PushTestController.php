<?php

namespace App\Http\Controllers\Api\V1\App;

use App\Http\Controllers\Controller;
use App\Models\UserApp;
use App\Services\AppNotificationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PushTestController extends Controller
{
    public function send(Request $request, AppNotificationService $notifications)
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:user_apps,id'],
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['nullable', 'string', 'max:250'],
            'type' => ['nullable', 'string', 'max:60'],
            'training_session_id' => ['nullable', 'integer', 'exists:training_sessions,id'],
            'training_id' => ['nullable', 'integer', 'exists:training_sessions,id'],
            'source' => ['nullable', Rule::in(['free', 'assigned'])],
        ]);

        $userApp = UserApp::query()->findOrFail((int) $data['user_id']);
        $trainingSessionId = $data['training_session_id'] ?? $data['training_id'] ?? null;
        $source = $data['source'] ?? 'assigned';
        $type = $data['type'] ?? ($source === 'free' ? 'training_free_created' : 'training_assigned');
        $title = $data['title'] ?? ($source === 'free' ? 'Nuevo entrenamiento libre' : 'Nuevo entrenamiento para ti');
        $body = $data['body'] ?? ($source === 'free' ? 'Tienes un nuevo entrenamiento libre.' : 'Tienes un nuevo entrenamiento asignado.');

        $notification = $notifications->sendToUserApp($userApp, $type, $title, $body, [
            'action' => 'open_training',
            'training_session_id' => $trainingSessionId,
            'source' => $source,
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Notificacion de prueba procesada con el contrato real.',
            'data' => [
                'id' => $notification->id,
                'user_id' => $notification->user_id,
                'type' => $notification->type,
                'title' => $notification->title,
                'status' => $notification->status,
                'provider' => $notification->provider,
                'payload' => $notification->data,
                'error' => $notification->error,
            ],
        ]);
    }
}
