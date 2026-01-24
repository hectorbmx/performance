<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\TrainingAssignment;
use App\Models\TrainingSection;
use App\Models\TrainingSectionResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class TrainingAssignmentsController extends Controller
{
    public function show(Request $request, TrainingAssignment $assignment)
    {
        $user = $request->user();
        $clientId = $user->client_id ?? null;

        if (!$clientId) {
            return response()->json(['ok' => false, 'message' => 'Cliente no identificado.'], 422);
        }

        if ((int)$assignment->client_id !== (int)$clientId) {
            return response()->json(['ok' => false, 'message' => 'No autorizado.'], 403);
        }

        // Carga training_session + secciones
        $session = $assignment->trainingSession()->first();

        $sections = TrainingSection::query()
            ->where('training_session_id', $assignment->training_session_id)
            ->orderBy('order')
            ->get(['id', 'training_session_id', 'order', 'name', 'description', 'video_url', 'accepts_results', 'result_type']);

        // Último resultado por sección (historial existe, pero UI MVP suele mostrar el último)
        $latestResults = TrainingSectionResult::query()
            ->where('training_assignment_id', $assignment->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('training_section_id')
            ->map(fn($rows) => $rows->first());

        $sectionsPayload = $sections->map(function ($s) use ($latestResults) {
            $r = $latestResults[$s->id] ?? null;

            return [
                'id' => $s->id,
                'order' => $s->order,
                'name' => $s->name,
                'description' => $s->description,
                'accepts_results' => (bool)$s->accepts_results,
                'video_url' => $s->video_url,
                // Nota: en tu BD actual result_type se usa como unidad (kg)
                'unit_default' => $s->result_type,
                'latest_result' => $r ? [
                    'id' => $r->id,
                    'result_type' => $r->result_type,
                    'value' => method_exists($r, 'normalizedValue') ? $r->normalizedValue() : (
                        $r->value_number ?? $r->value_time_seconds ?? $r->value_text ?? $r->value_bool ?? $r->value_json
                    ),
                    'unit' => $r->unit,
                    'notes' => $r->notes,
                    'created_at' => optional($r->created_at)->toISOString(),
                ] : null,
            ];
        });

        // Progreso
        $sectionsTotal = $sections->count();
        $sectionsWithResults = TrainingSectionResult::query()
            ->where('training_assignment_id', $assignment->id)
            ->distinct('training_section_id')
            ->count('training_section_id');

        $pct = $sectionsTotal > 0 ? (int) round(($sectionsWithResults / $sectionsTotal) * 100) : 0;
        $coverUrl = $session?->cover_image
            ? url(Storage::disk('public')->url($session->cover_image))
            : null;

        return response()->json([
            'ok' => true,
            'data' => [
                'assignment' => [
                    'id' => $assignment->id,
                    'status' => $assignment->status,
                    // 'scheduled_for' => optional($assignment->scheduled_for)->format('Y-m-d'),
                    'scheduled_for' => $assignment->scheduled_for?->format('Y-m-d'),


                ],
                
                'training_session' => $session ? [
                    'id' => $session->id,
                    'coach_id' => $session->coach_id,
                    'title' => $session->title,
                    // 'cover_image' => $session->co,
                    'cover_image' => $coverUrl,

                    'duration_minutes' => $session->duration_minutes,
                    'level' => $session->level,
                    'goal' => $session->goal,
                    'type' => $session->type,
                    'visibility' => $session->visibility,
                    'notes' => $session->notes,
                ] : null,
                'sections' => $sectionsPayload,
                'progress' => [
                    'sections_total' => $sectionsTotal,
                    'sections_with_results' => $sectionsWithResults,
                    'pct' => $pct,
                ],
            ],
        ]);
    }

    public function start(Request $request, TrainingAssignment $assignment)
    {
        $user = $request->user();
        $clientId = $user->client_id ?? null;

        if (!$clientId) {
            return response()->json(['ok' => false, 'message' => 'Cliente no identificado.'], 422);
        }

        if ((int)$assignment->client_id !== (int)$clientId) {
            return response()->json(['ok' => false, 'message' => 'No autorizado.'], 403);
        }

        if (in_array($assignment->status, ['completed', 'cancelled', 'skipped'], true)) {
            return response()->json(['ok' => false, 'message' => 'No se puede iniciar este entrenamiento.'], 422);
        }

        $assignment->update(['status' => 'in_progress']);

        return response()->json(['ok' => true, 'data' => ['status' => $assignment->status]]);
    }

    public function complete(Request $request, TrainingAssignment $assignment)
    {
        $user = $request->user();
        $clientId = $user->client_id ?? null;

        if (!$clientId) {
            return response()->json(['ok' => false, 'message' => 'Cliente no identificado.'], 422);
        }

        if ((int)$assignment->client_id !== (int)$clientId) {
            return response()->json(['ok' => false, 'message' => 'No autorizado.'], 403);
        }

        if ($assignment->status === 'cancelled') {
            return response()->json(['ok' => false, 'message' => 'No se puede completar un entrenamiento cancelado.'], 422);
        }

        $assignment->update(['status' => 'completed']);

        return response()->json(['ok' => true, 'data' => ['status' => $assignment->status]]);
    }

    //completar seccion
    public function completeSection(Request $request, TrainingAssignment $assignment, TrainingSection $section)
{
    $user = $request->user();
    $clientId = $user->client_id ?? null;

    if (!$clientId) {
        return response()->json(['ok' => false, 'message' => 'Cliente no identificado.'], 422);
    }

    // 1) Autorización: el assignment debe pertenecer al client
    if ((int)$assignment->client_id !== (int)$clientId) {
        return response()->json(['ok' => false, 'message' => 'No autorizado.'], 403);
    }

    // 2) Seguridad: la sección debe pertenecer al mismo training_session del assignment
    if ((int)$section->training_session_id !== (int)$assignment->training_session_id) {
        return response()->json(['ok' => false, 'message' => 'Sección inválida para este entrenamiento.'], 422);
    }

    // 3) Idempotencia: si ya existe resultado para esa sección, no duplicar
    $existing = TrainingSectionResult::query()
        ->where('training_assignment_id', $assignment->id)
        ->where('training_section_id', $section->id)
        ->exists();

    if ($existing) {
        return response()->json([
            'ok' => true,
            'message' => 'Sección ya estaba completada.',
        ]);
    }

    // 4) Crear resultado "bool" = true
    TrainingSectionResult::create([
        'training_assignment_id' => $assignment->id,
        'training_section_id'    => $section->id,
        'client_id'              => $clientId,

        'result_type'            => 'bool',
        'value_bool'             => 1,

        'unit'                   => null,
        'notes'                  => null,
        'recorded_at'            => now(),
    ]);

    return response()->json([
        'ok' => true,
        'message' => 'Sección completada.',
    ]);
}

}
