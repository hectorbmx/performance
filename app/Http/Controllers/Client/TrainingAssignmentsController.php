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

    // Carga training_session
    $session = $assignment->trainingSession()->first();

    $sections = TrainingSection::query()
        ->where('training_session_id', $assignment->training_session_id)
        ->orderBy('order')
        ->get(['id', 'training_session_id', 'order', 'name', 'description', 'video_url', 'accepts_results', 'result_type']);

    // ✅ Con UNIQUE ya es 1 fila por sección (no historial)
    $resultsBySection = TrainingSectionResult::query()
        ->where('training_assignment_id', $assignment->id)
        ->get()
        ->keyBy('training_section_id');

    // ✅ completions solo para secciones sin resultados
    $completionsBySection = DB::table('training_section_completions')
        ->where('training_assignment_id', $assignment->id)
        ->get()
        ->keyBy('training_section_id');

    $sectionsPayload = $sections->map(function ($s) use ($resultsBySection, $completionsBySection) {
        $r = $resultsBySection->get($s->id);

        $isCompleted = false;
        if ((bool)$s->accepts_results) {
            $isCompleted = (bool)$r;
        } else {
            $isCompleted = (bool)$completionsBySection->get($s->id);
        }

        return [
            'id' => $s->id,
            'order' => $s->order,
            'name' => $s->name,
            'description' => $s->description,
            'video_url' => $s->video_url,

            'accepts_results' => (bool)$s->accepts_results,
            // ✅ ESTE es el tipo que eligió el coach
            'result_type' => $s->result_type, // number|time|text|bool|json|null

            'is_completed' => $isCompleted,

            // ✅ Resultado si existe (solo si accepts_results=1)
            'result' => $r ? [
                'id' => $r->id,
                'result_type' => $r->result_type,
                'value' => method_exists($r, 'normalizedValue')
                    ? $r->normalizedValue()
                    : ($r->value_number ?? $r->value_time_seconds ?? $r->value_text ?? $r->value_bool ?? $r->value_json),
                'unit' => $r->unit,
                'notes' => $r->notes,
                'recorded_at' => optional($r->recorded_at)->toISOString(),
                'updated_at' => optional($r->updated_at)->toISOString(),
            ] : null,
        ];
    })->values();

    // Progreso real: completadas (por results o completions)
    $sectionsTotal = $sections->count();
    $sectionsWithResults = $resultsBySection->count();
    $sectionsCompletedNoResults = $completionsBySection->count();

    $sectionsCompleted = $sectionsWithResults + $sectionsCompletedNoResults;
    if ($sectionsCompleted > $sectionsTotal) $sectionsCompleted = $sectionsTotal;

    $pct = $sectionsTotal > 0 ? (int) round(($sectionsCompleted / $sectionsTotal) * 100) : 0;

    $coverUrl = $session?->cover_image
        ? url(Storage::disk('public')->url($session->cover_image))
        : null;

    return response()->json([
        'ok' => true,
        'data' => [
            'assignment' => [
                'id' => $assignment->id,
                'status' => $assignment->status,
                'scheduled_for' => $assignment->scheduled_for?->format('Y-m-d'),
            ],
            'training_session' => $session ? [
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
            ] : null,
            'sections' => $sectionsPayload,
            'progress' => [
                'sections_total' => $sectionsTotal,
                'sections_completed' => $sectionsCompleted,
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

   public function completeSection(Request $request, TrainingAssignment $assignment, TrainingSection $section)
{
    $user = $request->user();
    $clientId = $user->client_id ?? null;

    if (!$clientId) {
        return response()->json(['ok' => false, 'message' => 'Cliente no identificado.'], 422);
    }

    if ((int)$assignment->client_id !== (int)$clientId) {
        return response()->json(['ok' => false, 'message' => 'No autorizado.'], 403);
    }

    if ((int)$section->training_session_id !== (int)$assignment->training_session_id) {
        return response()->json(['ok' => false, 'message' => 'Sección inválida para este entrenamiento.'], 422);
    }

    // ✅ Si acepta resultados, NO se completa aquí
    if ((bool)$section->accepts_results) {
        return response()->json([
            'ok' => false,
            'message' => 'Esta sección requiere resultado. Guarda el resultado para completarla.',
        ], 422);
    }

    // ✅ Upsert idempotente en completions
    DB::table('training_section_completions')->updateOrInsert(
        [
            'training_assignment_id' => $assignment->id,
            'training_section_id' => $section->id,
        ],
        [
            'client_id' => $clientId,
            'completed_at' => now(),
            'updated_at' => now(),
            'created_at' => now(),
        ]
    );

    // opcional: mover a in_progress al primer avance
    if ($assignment->status === 'scheduled') {
        $assignment->update(['status' => 'in_progress']);
    }

    return response()->json([
        'ok' => true,
        'message' => 'Sección completada.',
    ]);
}

}
