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

        $completed = $resultSectionIds
            ->merge($completionSectionIds)
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
