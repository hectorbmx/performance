<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\TrainingAssignment;
use App\Models\TrainingLiftingSetLog;
use App\Models\TrainingSection;
use App\Models\TrainingSectionLiftingRow;
use App\Models\TrainingSectionResult;
use App\Models\TrainingSectionExerciseBlock;
use App\Services\TrainingAssignmentProgressService;
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
            ->with(['libraryVideos', 'liftingBlocks.rows'])
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

        $liftingLogsByRow = $assignment->liftingSetLogs()
            ->get()
            ->groupBy('lifting_row_id');

        // 2. Construcción del Payload Unificado
        $sectionsPayload = $sections->map(function ($s) use ($resultsBySection, $completionsBySection, $liftingLogsByRow) {
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
                'lifting_blocks' => $this->liftingBlocksPayload($s->liftingBlocks, $liftingLogsByRow),
                'is_completed' => $isCompleted,
                'result' => $r ? [
                    'id' => $r->id,
                    'value' => method_exists($r, 'normalizedValue') ? $r->normalizedValue() : $r->value_text,
                    'unit' => $r->unit,
                    'notes' => $r->notes,
                ] : null,
            ];
        })->values();

        // 3. Calculos de progreso
        $progress = app(TrainingAssignmentProgressService::class)->snapshot($assignment);

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
                    'sections_total' => $progress['sections_total'],
                    'sections_completed' => $progress['sections_completed'],
                    'sections_with_results' => $progress['sections_with_results'],
                    'pct' => $progress['pct'],
                ],
            ],
        ]);
    }

    private function liftingBlocksPayload($blocks, $logsByRow = null)
    {
        return $blocks->map(function (TrainingSectionExerciseBlock $block) use ($logsByRow) {
            return [
                'id' => $block->id,
                'exercise_catalog_id' => $block->exercise_catalog_id,
                'exercise_name' => $block->exercise_name,
                'notes' => $block->notes,
                'order' => $block->order,
                'rows' => $block->rows->map(function ($row) use ($logsByRow) {
                    $logs = $logsByRow?->get($row->id, collect()) ?? collect();
                    $logsBySet = $logs->keyBy('set_number');

                    return [
                        'id' => $row->id,
                        'percentage' => $row->percentage !== null ? (float)$row->percentage : null,
                        'reps' => $row->reps,
                        'sets' => $row->sets,
                        'rest_seconds' => $row->rest_seconds,
                        'notes' => $row->notes,
                        'order' => $row->order,
                        'set_statuses' => collect(range(1, max(1, (int)$row->sets)))->map(function ($setNumber) use ($logsBySet) {
                            $log = $logsBySet->get($setNumber);

                            return [
                                'set_number' => $setNumber,
                                'status' => $log?->status,
                                'actual_reps' => $log?->actual_reps,
                                'failure_reason' => $log?->failure_reason,
                                'notes' => $log?->notes,
                                'logged_at' => $log?->logged_at?->toIso8601String(),
                            ];
                        })->values(),
                    ];
                })->values(),
            ];
        })->values();
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

    public function saveLiftingSet(Request $request, TrainingAssignment $assignment)
    {
        $user = $request->user();
        $clientId = $user->client_id ?? null;

        if (!$clientId) {
            return response()->json(['ok' => false, 'message' => 'Cliente no identificado.'], 422);
        }

        if ((int)$assignment->client_id !== (int)$clientId) {
            return response()->json(['ok' => false, 'message' => 'No autorizado.'], 403);
        }

        if (in_array($assignment->status, ['cancelled', 'skipped'], true)) {
            return response()->json(['ok' => false, 'message' => 'No se puede modificar este entrenamiento.'], 422);
        }

        $data = $request->validate([
            'lifting_row_id' => ['required', 'integer', 'exists:training_section_lifting_rows,id'],
            'set_number' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'string', 'in:completed,failed,skipped'],
            'actual_reps' => ['nullable', 'integer', 'min:0', 'max:999'],
            'failure_reason' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $row = TrainingSectionLiftingRow::query()
            ->with('exerciseBlock.section')
            ->findOrFail($data['lifting_row_id']);

        $section = $row->exerciseBlock?->section;

        if (!$section || (int)$section->training_session_id !== (int)$assignment->training_session_id) {
            return response()->json(['ok' => false, 'message' => 'Serie invalida para este entrenamiento.'], 422);
        }

        if ((int)$data['set_number'] > (int)$row->sets) {
            return response()->json(['ok' => false, 'message' => 'El numero de serie excede la prescripcion.'], 422);
        }

        if ($data['status'] === TrainingLiftingSetLog::STATUS_COMPLETED && ($data['actual_reps'] ?? null) === null) {
            $data['actual_reps'] = $row->reps;
        }

        $log = TrainingLiftingSetLog::updateOrCreate(
            [
                'training_assignment_id' => $assignment->id,
                'lifting_row_id' => $row->id,
                'set_number' => $data['set_number'],
            ],
            [
                'status' => $data['status'],
                'actual_reps' => $data['actual_reps'] ?? null,
                'failure_reason' => $data['failure_reason'] ?? null,
                'notes' => $data['notes'] ?? null,
                'logged_at' => now(),
            ]
        );

        $progress = app(TrainingAssignmentProgressService::class)->syncStatus($assignment);

        return response()->json([
            'ok' => true,
            'data' => [
                'log' => [
                    'id' => $log->id,
                    'lifting_row_id' => $log->lifting_row_id,
                    'set_number' => $log->set_number,
                    'status' => $log->status,
                    'actual_reps' => $log->actual_reps,
                    'failure_reason' => $log->failure_reason,
                    'notes' => $log->notes,
                    'logged_at' => $log->logged_at?->toIso8601String(),
                ],
                'assignment' => [
                    'id' => $assignment->id,
                    'status' => $assignment->status,
                ],
                'progress' => $progress,
            ],
            'message' => 'Serie registrada.',
        ]);
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

    // Sincroniza el status de la asignacion con el avance real.
    $progress = app(TrainingAssignmentProgressService::class)->syncStatus($assignment);

    return response()->json([
        'ok' => true,
        'data' => [
            'status' => $assignment->status,
            'progress' => $progress,
        ],
        'message' => 'Sección completada.',
    ]);
}

}
