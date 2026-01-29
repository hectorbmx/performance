<?php

namespace App\Http\Controllers\Api\V1\App;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class PushTestController extends Controller
{
    public function send(Request $request)
    {
        // 1. Validación de entrada
        $data = $request->validate([
            'user_id'     => ['required', 'integer'],
            'title'       => ['nullable', 'string', 'max:120'],
            'body'        => ['nullable', 'string', 'max:250'],
            'type'        => ['required', 'string', 'max:60'],
            'training_id' => ['nullable', 'integer'],
        ]);

        $title = $data['title'] ?? 'Entrenamiento asignado';
        $body  = $data['body']  ?? 'Tienes un entrenamiento hoy';

        // 2. Obtener tokens activos del usuario
        $tokens = DB::table('user_devices')
            ->where('user_id', $data['user_id'])
            ->where('is_enabled', 1)
            ->whereNotNull('token')
            ->pluck('token')
            ->filter()
            ->values()
            ->all();

        if (empty($tokens)) {
            return response()->json([
                'ok' => false,
                'message' => 'No hay dispositivos activos para este usuario',
            ], 422);
        }

        // 3. Construir el mensaje
        $message = CloudMessage::new()
            ->withNotification(Notification::create($title, $body))
            ->withData([
                'type' => $data['type'],
                'training_id' => isset($data['training_id']) ? (string)$data['training_id'] : '',
            ]);

        /** @var \Kreait\Firebase\Messaging $messaging */
        $messaging = app('firebase.messaging');

        // 4. Envío Multicast y obtención de reporte
        try {
            $report = $messaging->sendMulticast($message, $tokens);
            
            $failures = $report->failures(); // Objeto SendReports (colección de objetos)

            return response()->json([
                'ok' => true,
                'message' => 'Proceso de envío finalizado',
                'summary' => [
                    'requested_tokens' => count($tokens),
                    'successes' => $report->successes()->count(),
                    'failures'  => $failures->count(),
                ],
                'errors' => collect($failures->getItems())->map(function ($failure) {
                    /** @var \Kreait\Firebase\Messaging\SendReport $failure */
                    return [
                        'token' => $failure->target()->value(),
                        'error' => $failure->error()->getMessage(),
                    ];
                })->values(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Error crítico en el envío: ' . $e->getMessage(),
            ], 500);
        }
    }
}