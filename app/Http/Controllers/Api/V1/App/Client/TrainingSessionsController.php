<?php

namespace App\Http\Controllers\Api\V1\App\Client;

use App\Http\Controllers\Controller;
use App\Models\TrainingSession;
use App\Models\TrainingAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TrainingSessionsController extends Controller
{
    /**
     * Mostrar detalle de un entrenamiento LIBRE (free)
     */
    public function show(Request $request, TrainingSession $session)
    {
        // 🔒 Seguridad: solo entrenamientos libres y no eliminados
        if ($session->visibility !== 'free' || $session->deleted_at !== null) {
            return response()->json([
                'ok' => false,
                'message' => 'Entrenamiento no disponible.',
            ], 404);
        }

        // Cargar secciones ordenadas
        $session->load([
            // 'sections' => fn ($q) => $q->orderBy('order'),
            'sections' => fn ($q) => $q->orderBy('order')->with('unit'),

        ]);

        $coverUrl = $session->cover_image
            ? url(Storage::disk('public')->url($session->cover_image))
            : null;

        return response()->json([
            'ok' => true,
            'data' => [
                // ⚠️ No hay assignment
                'assignment_id' => null,
                'source' => 'free',
                'status' => null,
                'scheduled_for' => optional($session->scheduled_at)->format('Y-m-d'),

                'training_session' => [
                    'id' => $session->id,
                    'coach_id' => $session->coach_id,
                    'title' => $session->title,
                    'cover_image' => $coverUrl,
                    'duration_minutes' => $session->duration_minutes,
                    'level' => $session->level,
                    'goal' => $session->goal,
                    'type' => $session->type,
                    'visibility' => $session->visibility,
                    'notes' => $session->notes,
                ],

                'sections' => $session->sections->map(fn ($s) => [
                    'id' => $s->id,
                    'order' => $s->order,
                    'name' => $s->name,
                    'description' => $s->description,
                    'video_url' => $s->video_url,
                    'accepts_results' => (bool)$s->accepts_results,
                    'result_type' => $s->result_type,
                    'unit' => $s->unit?->symbol,
                ]),

                // En free no hay progreso real
                'progress' => [
                    'sections_total' => $session->sections->count(),
                    'sections_with_results' => 0,
                    'pct' => 0,
                ],
            ],
        ]);
    }
    public function start(Request $request, TrainingSession $trainingSession)
        {
            $user = $request->user();
            $clientId = $user->client_id ?? null;

            if (!$clientId) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Cliente no identificado.',
                ], 422);
            }

            // Seguridad: solo FREE
            if (($trainingSession->visibility ?? null) !== 'free') {
                return response()->json([
                    'ok' => false,
                    'message' => 'Este entrenamiento no es libre.',
                ], 403);
            }

            // Soft delete guard (por si el binding no lo filtra)
            if (!empty($trainingSession->deleted_at)) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Entrenamiento no disponible.',
                ], 404);
            }

            // Regla para scheduled_for en free:
            // ✅ Recomendación: usar el scheduled_at del training si existe, si no, hoy.
            $scheduledFor = $trainingSession->scheduled_at
                ? $trainingSession->scheduled_at->format('Y-m-d')
                : now()->toDateString();

            // Buscar si ya existe assignment para ese client + session + fecha
            $assignment = TrainingAssignment::query()
                ->where('client_id', $clientId)
                ->where('training_session_id', $trainingSession->id)
                ->whereDate('scheduled_for', $scheduledFor)
                ->first();

            if (!$assignment) {
                $assignment = TrainingAssignment::create([
                    'training_session_id' => $trainingSession->id,
                    'client_id' => $clientId,
                    'scheduled_for' => $scheduledFor,
                    'status' => 'in_progress', // o 'scheduled' si prefieres
                ]);
            } else {
                // Si existe y estaba scheduled, opcionalmente lo pasas a in_progress
                if ($assignment->status === 'scheduled') {
                    $assignment->status = 'in_progress';
                    $assignment->save();
                }
            }

            return response()->json([
                'ok' => true,
                'data' => [
                    'assignment_id' => (int)$assignment->id,
                    'scheduled_for' => $assignment->scheduled_for?->format('Y-m-d') ?? $scheduledFor,
                    'status' => $assignment->status,
                ],
            ]);
        }
}
