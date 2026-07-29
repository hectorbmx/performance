<?php

namespace App\Services;

use App\Models\TrainingAssignment;
use Illuminate\Support\Facades\DB;

class TrainingAssignmentProgressService
{
    public function snapshot(TrainingAssignment $assignment): array
    {
        $sectionIds = DB::table('training_sections')
            ->where('training_session_id', $assignment->training_session_id)
            ->pluck('id');

        $total = $sectionIds->count();

        if ($total === 0) {
            return [
                'sections_total' => 0,
                'sections_completed' => 0,
                'sections_with_results' => 0,
                'pct' => 0,
            ];
        }

        $resultSectionIds = DB::table('training_section_results')
            ->where('training_assignment_id', $assignment->id)
            ->whereIn('training_section_id', $sectionIds)
            ->pluck('training_section_id');

        $completionSectionIds = DB::table('training_section_completions')
            ->where('training_assignment_id', $assignment->id)
            ->whereIn('training_section_id', $sectionIds)
            ->pluck('training_section_id');

        $liftingSectionIds = DB::table('training_section_exercise_blocks as blocks')
            ->join('training_sections as sections', 'sections.id', '=', 'blocks.training_section_id')
            ->where('sections.training_session_id', $assignment->training_session_id)
            ->distinct()
            ->pluck('blocks.training_section_id');

        $completedLiftingSectionIds = $liftingSectionIds->filter(function ($sectionId) use ($assignment) {
            $requiredSets = (int) DB::table('training_section_lifting_rows as rows')
                ->join('training_section_exercise_blocks as blocks', 'blocks.id', '=', 'rows.exercise_block_id')
                ->where('blocks.training_section_id', $sectionId)
                ->sum('rows.sets');

            if ($requiredSets <= 0) {
                return false;
            }

            $loggedSets = DB::table('training_lifting_set_logs as logs')
                ->join('training_section_lifting_rows as rows', 'rows.id', '=', 'logs.lifting_row_id')
                ->join('training_section_exercise_blocks as blocks', 'blocks.id', '=', 'rows.exercise_block_id')
                ->where('logs.training_assignment_id', $assignment->id)
                ->where('blocks.training_section_id', $sectionId)
                ->whereIn('logs.status', ['completed', 'failed', 'skipped'])
                ->distinct()
                ->count(DB::raw("CONCAT(logs.lifting_row_id, ':', logs.set_number)"));

            return $loggedSets >= $requiredSets;
        })->values();

        $completed = $resultSectionIds
            ->merge($completionSectionIds)
            ->merge($completedLiftingSectionIds)
            ->unique()
            ->count();

        $completed = min($completed, $total);

        return [
            'sections_total' => $total,
            'sections_completed' => $completed,
            'sections_with_results' => $completed,
            'pct' => (int) round(($completed / $total) * 100),
        ];
    }

    public function syncStatus(TrainingAssignment $assignment): array
    {
        $progress = $this->snapshot($assignment);

        if (
            $progress['sections_total'] > 0
            && $progress['sections_completed'] >= $progress['sections_total']
            && !in_array($assignment->status, ['completed', 'cancelled', 'skipped'], true)
        ) {
            $assignment->update(['status' => 'completed']);
            $assignment->refresh();

            return $progress;
        }

        if ($assignment->status === 'scheduled' && $progress['sections_completed'] > 0) {
            $assignment->update(['status' => 'in_progress']);
            $assignment->refresh();
        }

        return $progress;
    }
}
