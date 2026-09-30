<?php

namespace App\Services;

use App\Models\Client;
use App\Models\TrainingAssignment;

class ClientLiftingPerformanceService
{
    private LiftingVolumeSummaryService $volumeSummaryService;

    public function __construct(
        LiftingVolumeSummaryService $volumeSummaryService
    ) {
        $this->volumeSummaryService = $volumeSummaryService;
    }

    public function forClient(Client $client, int $limit = 8): array
    {
        $assignments = TrainingAssignment::query()
            ->where('client_id', $client->id)
            ->whereHas('trainingSession.sections.liftingBlocks.rows')
            ->with([
                'trainingSession.sections.liftingBlocks.rows',
                'liftingSetLogs',
            ])
            ->latest('scheduled_for')
            ->latest('id')
            ->limit($limit)
            ->get();

        $recentAssignments = $assignments
            ->map(function (TrainingAssignment $assignment) {
                $summary = $this->volumeSummaryService->forAssignment($assignment);
                $session = $assignment->trainingSession;

                return [
                    'assignment_id' => $assignment->id,
                    'training_session_id' => $assignment->training_session_id,
                    'title' => $session?->title ?? 'Entrenamiento sin titulo',
                    'scheduled_for' => $assignment->scheduled_for?->format('Y-m-d')
                        ?? $session?->scheduled_at?->format('Y-m-d'),
                    'status' => $assignment->status,
                    'summary' => $summary,
                ];
            })
            ->values();

        $summary = $this->emptySummary();
        $zones = [];
        $byExercise = [];

        foreach ($recentAssignments as $item) {
            $assignmentSummary = $item['summary'];
            $summary['assignments_count']++;

            foreach ([
                'sets_prescribed',
                'sets_executed',
                'sets_completed',
                'sets_failed',
                'sets_skipped',
                'sets_pending',
                'reps_prescribed',
                'reps_executed',
                'relative_volume',
            ] as $key) {
                $summary[$key] += $assignmentSummary[$key] ?? 0;
            }

            foreach ($assignmentSummary['zones'] ?? [] as $zone) {
                $zoneKey = $zone['key'];

                if (!isset($zones[$zoneKey])) {
                    $zones[$zoneKey] = [
                        'key' => $zoneKey,
                        'label' => $zone['label'],
                        'prescribed_reps' => 0,
                        'executed_reps' => 0,
                        'adherence_pct' => 0,
                    ];
                }

                $zones[$zoneKey]['prescribed_reps'] += $zone['prescribed_reps'];
                $zones[$zoneKey]['executed_reps'] += $zone['executed_reps'];
            }

            foreach ($assignmentSummary['by_exercise'] ?? [] as $exercise) {
                $exerciseKey = $this->exerciseKey($exercise['exercise_name']);

                if (!isset($byExercise[$exerciseKey])) {
                    $byExercise[$exerciseKey] = [
                        'exercise_name' => $exercise['exercise_name'],
                        'sets_prescribed' => 0,
                        'sets_executed' => 0,
                        'reps_prescribed' => 0,
                        'reps_executed' => 0,
                        'rep_adherence_pct' => 0,
                        'relative_volume' => 0.0,
                    ];
                }

                foreach ([
                    'sets_prescribed',
                    'sets_executed',
                    'reps_prescribed',
                    'reps_executed',
                    'relative_volume',
                ] as $key) {
                    $byExercise[$exerciseKey][$key] += $exercise[$key] ?? 0;
                }
            }
        }

        $summary['rep_adherence_pct'] = $summary['reps_prescribed'] > 0
            ? (int) round(($summary['reps_executed'] / $summary['reps_prescribed']) * 100)
            : 0;
        $summary['relative_volume'] = round($summary['relative_volume'], 2);

        return [
            'has_data' => $recentAssignments->isNotEmpty(),
            'summary' => $summary,
            'recent_assignments' => $recentAssignments->all(),
            'zones' => $this->finalizeZones($zones),
            'by_exercise' => $this->finalizeExercises($byExercise),
        ];
    }

    private function emptySummary(): array
    {
        return [
            'assignments_count' => 0,
            'sets_prescribed' => 0,
            'sets_executed' => 0,
            'sets_completed' => 0,
            'sets_failed' => 0,
            'sets_skipped' => 0,
            'sets_pending' => 0,
            'reps_prescribed' => 0,
            'reps_executed' => 0,
            'rep_adherence_pct' => 0,
            'relative_volume' => 0.0,
        ];
    }

    private function finalizeZones(array $zones): array
    {
        return collect($zones)
            ->map(function (array $zone) {
                $zone['adherence_pct'] = $zone['prescribed_reps'] > 0
                    ? (int) round(($zone['executed_reps'] / $zone['prescribed_reps']) * 100)
                    : 0;

                return $zone;
            })
            ->values()
            ->all();
    }

    private function finalizeExercises(array $exercises): array
    {
        return collect($exercises)
            ->map(function (array $exercise) {
                $exercise['rep_adherence_pct'] = $exercise['reps_prescribed'] > 0
                    ? (int) round(($exercise['reps_executed'] / $exercise['reps_prescribed']) * 100)
                    : 0;
                $exercise['relative_volume'] = round($exercise['relative_volume'], 2);

                return $exercise;
            })
            ->sortByDesc('reps_executed')
            ->values()
            ->all();
    }

    private function exerciseKey(string $exerciseName): string
    {
        $normalized = strtolower(trim($exerciseName));
        $normalized = preg_replace('/[^a-z0-9]+/', '_', $normalized) ?: 'unknown';

        return trim($normalized, '_') ?: 'unknown';
    }
}
