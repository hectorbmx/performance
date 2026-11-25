<?php

namespace App\Services;

use App\Models\TrainingSession;
use App\Models\TrainingSectionExerciseBlock;
use App\Models\WorkoutExercise;
use Illuminate\Support\Str;

class WorkoutPrescriptionSnapshot
{
    private function catalogForBlock(TrainingSectionExerciseBlock $block, int $coachId): ?array
    {
        if (!$block->workout_exercise_id) return null;
        $exercise = WorkoutExercise::visibleForCoach($coachId)->where('is_active', true)->with('terms')->find($block->workout_exercise_id);
        abort_unless($exercise, 422, 'El ejercicio vinculado ya no está disponible para este coach.');
        return [
            'code' => $exercise->code, 'names' => $exercise->names, 'load_mode' => $exercise->load_mode,
            'unilateral' => $exercise->unilateral, 'review_status' => $exercise->review_status,
            'terms' => $exercise->terms->map(fn ($term) => ['kind' => $term->kind, 'code' => $term->code, 'role' => $term->pivot->role])->values()->all(),
        ];
    }

    public function capture(TrainingSession $training): array
    {
        // Locking reads see the same current revision after the parent lock,
        // including under MySQL REPEATABLE READ. Call within the start transaction.
        $training->load([
            'sections' => fn ($query) => $query->lockForUpdate(),
            'sections.liftingBlocks' => fn ($query) => $query->lockForUpdate(),
            'sections.liftingBlocks.rows' => fn ($query) => $query->lockForUpdate(),
        ]);

        return [
            'title' => $training->title,
            'level' => $training->level,
            'goal' => $training->goal,
            'type' => $training->type,
            'duration_minutes' => $training->duration_minutes,
            'notes' => $training->notes,
            'source' => 'existing_assignment',
            'sections' => $training->sections->map(fn ($section) => [
                'uuid' => (string) Str::uuid(),
                'source_section_id' => $section->id,
                'name' => $section->name,
                'description' => $section->description,
                'order' => $section->order,
                'result_type' => $section->result_type,
                'exercises' => $section->liftingBlocks->map(fn ($block) => [
                    'uuid' => (string) Str::uuid(),
                    'source_block_id' => $block->id,
                    'exercise_catalog_id' => $block->exercise_catalog_id, // legacy, not canonical
                    'workout_exercise_id' => $block->workout_exercise_id,
                    'catalog' => $this->catalogForBlock($block, (int) $training->coach_id),
                    'name' => $block->exercise_name,
                    'notes' => $block->notes,
                    'order' => $block->order,
                    // Legacy text alone cannot establish load semantics.
                    'load_mode' => null,
                    'sets' => $block->rows->flatMap(function ($row) {
                        $sets = [];
                        for ($number = 1; $number <= (int) $row->sets; $number++) {
                            $sets[] = [
                                'uuid' => (string) Str::uuid(),
                                'source_row_id' => $row->id,
                                'set_number' => $number,
                                'row_order' => $row->order,
                                'type' => null,
                                'prescribed' => [
                                    'reps_min' => $row->reps,
                                    'reps_max' => $row->reps,
                                    'percentage' => $row->percentage,
                                    'rest_seconds' => $row->rest_seconds,
                                    'rir' => null,
                                    'load' => null,
                                ],
                                'recommended' => null,
                                'notes' => $row->notes,
                            ];
                        }
                        return $sets;
                    })->values()->all(),
                ])->values()->all(),
            ])->values()->all(),
        ];
    }
}
