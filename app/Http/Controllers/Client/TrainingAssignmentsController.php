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

        $session = $assignment->trainingSession()->first();

        // 1. Cargamos secciones con libraryVideos (usamos get() sin filtros para asegurar que traiga los IDs de relación)
        $sections = TrainingSection::query()
            ->where('training_session_id', $assignment->training_session_id)
            ->with(['libraryVideos']) 
            ->orderBy('order')
            ->get();

        $resultsBySection = TrainingSectionResult::query()
            ->where('training_assignment_id', $assignment->id)
            ->get()
            ->keyBy('training_section_id');

        $completionsBySection = DB::table('training_section_completions')
            ->where('training_assignment_id', $assignment->id)
            ->get()
            ->keyBy('training_section_id');

        // 2. Construcción del Payload Unificado
        $sectionsPayload = $sections->map(function ($s) use ($resultsBySection, $completionsBySection) {
            $r = $resultsBySection->get($s->id);

            // --- Lógica de Unificación de Videos ---
            $allVideos = collect();

            // Opción A: Video directo (el de la columna video_url)
            if (!empty($s->video_url)) {
                $allVideos->push([
                    'id' => null,
                    'name' => 'Video de Referencia',
                    'youtube_url' => $s->video_url,
                    'source' => 'direct_url',
                    'order' => 0
                ]);
            }

            // Opción B: Videos de la Librería (Relación belongsToMany)
            foreach ($s->libraryVideos as $lv) {
                $allVideos->push([
                    'id' => $lv->id,
                    'name' => $lv->name,
                    'youtube_url' => $lv->youtube_url,
                    'youtube_id' => $lv->youtube_id,
                    'source' => 'library',
                    'order' => $lv->pivot->order ?? 0,
                    'notes' => $lv->pivot->notes ?? null,
                ]);
            }

            // Ordenar por el campo order (opcional)
            $sortedVideos = $allVideos->sortBy('order')->values();

            $isCompleted = (bool)$s->accepts_results 
                ? (bool)$r 
                : (bool)$completionsBySection->get($s->id);

            return [
                'id' => $s->id,
                'order' => $s->order,
                'name' => $s->name,
                'description' => $s->description,
                
                // ✅ Aquí enviamos todos los videos unificados para tus botones rojos
                'videos' => $sortedVideos,

                'accepts_results' => (bool)$s->accepts_results,
                'result_type' => $s->result_type,
                'is_completed' => $isCompleted,
                'result' => $r ? [
                    'id' => $r->id,
                    'value' => method_exists($r, 'normalizedValue') ? $r->normalizedValue() : $r->value_text,
                    'unit' => $r->unit,
                    'notes' => $r->notes,
                ] : null,
            ];
        })->values();

        // 3. Cálculos de progreso
        $sectionsTotal = $sections->count();
        $sectionsCompleted = $resultsBySection->count() + $completionsBySection->count();
        if ($sectionsCompleted > $sectionsTotal) $sectionsCompleted = $sectionsTotal;
        $pct = $sectionsTotal > 0 ? (int) round(($sectionsCompleted / $sectionsTotal) * 100) : 0;

        $coverUrl = $session?->cover_image ? url(Storage::disk('public')->url($session->cover_image)) : null;

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
                    'title' => $session->title,
                    'cover_image' => $coverUrl,
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
