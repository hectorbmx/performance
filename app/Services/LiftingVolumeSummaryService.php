<?php

namespace App\Services;

use App\Models\TrainingAssignment;
use App\Models\TrainingLiftingSetLog;
use Illuminate\Support\Collection;

class LiftingVolumeSummaryService
{
    private const METRIC_ALIASES = [
        'back_squat_1rm' => [
            'back squat',
            'backsquat',
            'box back squat',
            'box bak squat',
            'box squat',
            'recuperaciones back squat',
        ],
        'front_squat_1rm' => [
            'front squat',
            'frontsquat',
            'recuperaciones front squat',
        ],
        'snatch_1rm' => [
            'snatch',
            'full snatch',
            'power snatch',
            'hang snatch',
            'hang power snatch',
        ],
        'clean_1rm' => [
            'clean',
            'full clean',
            'clean full',
            'power clean',
            'hang clean',
            'clean from blocks',
            'clean from blcks',
            'cleans',
        ],
        'jerk_1rm' => [
            'jerk',
            'split jerk',
            'power jerk',
        ],
    ];

    private const SILENT_UNMAPPED_ALIASES = [
        'recuperaciones',
        'recovery',
        'recoveries',
    ];

    private const ZONES = [
        'lt_60' => ['label' => '<60%', 'min' => null, 'max' => 59.999],
        '60_69' => ['label' => '60-69%', 'min' => 60, 'max' => 69.999],
        '70_79' => ['label' => '70-79%', 'min' => 70, 'max' => 79.999],
        '80_89' => ['label' => '80-89%', 'min' => 80, 'max' => 89.999],
        '90_94' => ['label' => '90-94%', 'min' => 90, 'max' => 94.999],
        '95_plus' => ['label' => '95%+', 'min' => 95, 'max' => null],
        'unknown' => ['label' => 'Sin %', 'min' => null, 'max' => null],
    ];

    public function forAssignment(TrainingAssignment $assignment): array
    {
        $assignment->loadMissing([
            'trainingSession.sections.liftingBlocks.rows',
            'liftingSetLogs',
        ]);

        $logsByRow = $assignment->liftingSetLogs
            ->groupBy('lifting_row_id')
            ->map(fn ($logs) => $logs->keyBy('set_number'));
        $maxesByMetricCode = $this->latestMaxesByMetricCode($assignment);

        $summary = $this->emptySummary();
        $zones = $this->emptyZones();
        $byExercise = [];

        foreach ($assignment->trainingSession?->sections ?? [] as $section) {
            foreach ($section->liftingBlocks as $block) {
                $exerciseName = trim((string) $block->exercise_name);
                $exerciseKey = $this->exerciseKey($exerciseName);

                if (!isset($byExercise[$exerciseKey])) {
                    $byExercise[$exerciseKey] = $this->emptyExerciseSummary($exerciseName);
                }

                foreach ($block->rows as $row) {
                    $rowLogs = $logsByRow->get($row->id, collect());
                    $sets = max(0, (int) $row->sets);
                    $rowReps = max(0, (int) $row->reps);
                    $percentage = $row->percentage !== null ? (float) $row->percentage : null;
                    $zoneKey = $this->zoneKey($percentage);
                    $prescribedReps = $rowReps * $sets;
                    $metricCode = $this->metricCodeForExercise($exerciseName);
                    $maxRecord = $metricCode ? $maxesByMetricCode->get($metricCode) : null;
                    $maxValue = $maxRecord ? (float) $maxRecord->value : null;

                    $summary['sets_prescribed'] += $sets;
                    $summary['reps_prescribed'] += $prescribedReps;
                    $zones[$zoneKey]['prescribed_reps'] += $prescribedReps;
                    $byExercise[$exerciseKey]['sets_prescribed'] += $sets;
                    $byExercise[$exerciseKey]['reps_prescribed'] += $prescribedReps;
                    $byExercise[$exerciseKey]['metric_code'] = $metricCode;

                    for ($setNumber = 1; $setNumber <= $sets; $setNumber++) {
                        $log = $rowLogs->get($setNumber);

                        if (!$log) {
                            continue;
                        }

                        if (!in_array($log->status, [
                            TrainingLiftingSetLog::STATUS_COMPLETED,
                            TrainingLiftingSetLog::STATUS_FAILED,
                            TrainingLiftingSetLog::STATUS_SKIPPED,
                        ], true)) {
                            continue;
                        }

                        $executedReps = $this->executedRepsForLog($log, $rowReps);

                        $summary['sets_executed']++;
                        $summary['reps_executed'] += $executedReps;
                        $zones[$zoneKey]['executed_reps'] += $executedReps;
                        $byExercise[$exerciseKey]['sets_executed']++;
                        $byExercise[$exerciseKey]['reps_executed'] += $executedReps;

                        if ($log->status === TrainingLiftingSetLog::STATUS_COMPLETED) {
                            $summary['sets_completed']++;
                        } elseif ($log->status === TrainingLiftingSetLog::STATUS_FAILED) {
                            $summary['sets_failed']++;
                        } elseif ($log->status === TrainingLiftingSetLog::STATUS_SKIPPED) {
                            $summary['sets_skipped']++;
                        }

                        if ($percentage !== null) {
                            $relativeVolume = $percentage * $executedReps;
                            $summary['relative_volume'] += $relativeVolume;
                            $summary['intensity_reps'] += $executedReps;
                            $byExercise[$exerciseKey]['relative_volume'] += $relativeVolume;
                            $byExercise[$exerciseKey]['intensity_reps'] += $executedReps;

                            if ($metricCode && $maxValue !== null) {
                                $tonnage = $maxValue * ($percentage / 100) * $executedReps;
                                $summary['estimated_tonnage'] += $tonnage;
                                $byExercise[$exerciseKey]['estimated_tonnage'] += $tonnage;
                            } elseif ($metricCode && $executedReps > 0) {
                                $summary['missing_max_metrics'][$metricCode] = $metricCode;
                            } elseif (!$metricCode && $executedReps > 0 && !$this->isSilentUnmappedExercise($exerciseName)) {
                                $summary['unmapped_exercises'][$exerciseKey] = $exerciseName !== '' ? $exerciseName : 'Sin ejercicio';
                            }
                        }
                    }
                }
            }
        }

        $summary['sets_pending'] = max(0, $summary['sets_prescribed'] - $summary['sets_executed']);
        $summary['rep_adherence_pct'] = $summary['reps_prescribed'] > 0
            ? (int) round(($summary['reps_executed'] / $summary['reps_prescribed']) * 100)
            : 0;
        $summary['average_intensity_pct'] = $summary['intensity_reps'] > 0
            ? round($summary['relative_volume'] / $summary['intensity_reps'], 1)
            : null;
        $summary['relative_volume'] = round($summary['relative_volume'], 2);
        $summary['estimated_tonnage'] = round($summary['estimated_tonnage'], 2);
        $summary['missing_max_metrics'] = array_values($summary['missing_max_metrics']);
        $summary['unmapped_exercises'] = array_values($summary['unmapped_exercises']);
        $summary['chart'] = $this->chart($summary);
        $summary['zones'] = $this->finalizeZones($zones, $summary['reps_executed']);
        $summary['by_exercise'] = $this->finalizeExercises($byExercise);

        return $summary;
    }

    private function emptySummary(): array
    {
        return [
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
            'intensity_reps' => 0,
            'average_intensity_pct' => null,
            'estimated_tonnage' => 0.0,
            'tonnage_unit' => 'kg',
            'missing_max_metrics' => [],
            'unmapped_exercises' => [],
            'chart' => [
                'reps' => [],
                'sets' => [],
            ],
            'zones' => [],
            'by_exercise' => [],
        ];
    }

    private function latestMaxesByMetricCode(TrainingAssignment $assignment): Collection
    {
        if (!$assignment->client_id) {
            return collect();
        }

        return \App\Models\ClientMetricRecord::query()
            ->join('training_metrics', 'training_metrics.id', '=', 'client_metric_records.training_metric_id')
            ->where('client_metric_records.client_id', $assignment->client_id)
            ->whereIn('training_metrics.code', array_keys(self::METRIC_ALIASES))
            ->orderByDesc('client_metric_records.recorded_at')
            ->orderByDesc('client_metric_records.id')
            ->get([
                'training_metrics.code',
                'training_metrics.unit',
                'client_metric_records.value',
                'client_metric_records.recorded_at',
            ])
            ->unique('code')
            ->keyBy('code');
    }

    private function executedRepsForLog(TrainingLiftingSetLog $log, int $rowReps): int
    {
        if ($log->status === TrainingLiftingSetLog::STATUS_COMPLETED) {
            return $log->actual_reps ?? $rowReps;
        }

        if ($log->status === TrainingLiftingSetLog::STATUS_FAILED) {
            return $log->actual_reps ?? 0;
        }

        return 0;
    }

    private function chart(array $summary): array
    {
        return [
            'reps' => [
                ['label' => 'Programadas', 'value' => $summary['reps_prescribed']],
                ['label' => 'Realizadas', 'value' => $summary['reps_executed']],
            ],
            'sets' => [
                ['label' => 'Completados', 'value' => $summary['sets_completed']],
                ['label' => 'Fallados', 'value' => $summary['sets_failed']],
                ['label' => 'Saltados', 'value' => $summary['sets_skipped']],
                ['label' => 'Pendientes', 'value' => $summary['sets_pending']],
            ],
        ];
    }

    private function emptyZones(): array
    {
        $zones = [];

        foreach (self::ZONES as $key => $zone) {
            $zones[$key] = [
                'key' => $key,
                'label' => $zone['label'],
                'prescribed_reps' => 0,
                'executed_reps' => 0,
                'adherence_pct' => 0,
                'distribution_pct' => 0,
            ];
        }

        return $zones;
    }

    private function finalizeZones(array $zones, int $totalExecutedReps): array
    {
        return collect($zones)
            ->map(function (array $zone) use ($totalExecutedReps) {
                $zone['adherence_pct'] = $zone['prescribed_reps'] > 0
                    ? (int) round(($zone['executed_reps'] / $zone['prescribed_reps']) * 100)
                    : 0;
                $zone['distribution_pct'] = $totalExecutedReps > 0
                    ? (int) round(($zone['executed_reps'] / $totalExecutedReps) * 100)
                    : 0;

                return $zone;
            })
            ->values()
            ->all();
    }

    private function zoneKey(?float $percentage): string
    {
        if ($percentage === null) {
            return 'unknown';
        }

        foreach (self::ZONES as $key => $zone) {
            if ($key === 'unknown') {
                continue;
            }

            if (
                ($zone['min'] === null || $percentage >= $zone['min'])
                && ($zone['max'] === null || $percentage <= $zone['max'])
            ) {
                return $key;
            }
        }

        return 'unknown';
    }

    private function exerciseKey(string $exerciseName): string
    {
        $normalized = strtolower(trim($exerciseName));
        $normalized = preg_replace('/[^a-z0-9]+/', '_', $normalized) ?: 'unknown';

        return trim($normalized, '_') ?: 'unknown';
    }

    private function metricCodeForExercise(string $exerciseName): ?string
    {
        $normalized = $this->normalizeExerciseName($exerciseName);

        foreach (self::METRIC_ALIASES as $metricCode => $aliases) {
            foreach ($aliases as $alias) {
                if (str_contains($normalized, $this->normalizeExerciseName($alias))) {
                    return $metricCode;
                }
            }
        }

        return null;
    }

    private function isSilentUnmappedExercise(string $exerciseName): bool
    {
        $normalized = $this->normalizeExerciseName($exerciseName);

        foreach (self::SILENT_UNMAPPED_ALIASES as $alias) {
            if ($normalized === $this->normalizeExerciseName($alias)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeExerciseName(string $exerciseName): string
    {
        $normalized = strtolower(trim($exerciseName));
        $normalized = preg_replace('/[^a-z0-9]+/', ' ', $normalized) ?: '';

        return trim(preg_replace('/\s+/', ' ', $normalized) ?: '');
    }

    private function emptyExerciseSummary(string $exerciseName): array
    {
        return [
            'exercise_name' => $exerciseName !== '' ? $exerciseName : 'Sin ejercicio',
            'sets_prescribed' => 0,
            'sets_executed' => 0,
            'reps_prescribed' => 0,
            'reps_executed' => 0,
            'rep_adherence_pct' => 0,
            'relative_volume' => 0.0,
            'intensity_reps' => 0,
            'average_intensity_pct' => null,
            'estimated_tonnage' => 0.0,
            'metric_code' => null,
        ];
    }

    private function finalizeExercises(array $exercises): array
    {
        return collect($exercises)
            ->map(function (array $exercise) {
                $exercise['rep_adherence_pct'] = $exercise['reps_prescribed'] > 0
                    ? (int) round(($exercise['reps_executed'] / $exercise['reps_prescribed']) * 100)
                    : 0;
                $exercise['average_intensity_pct'] = $exercise['intensity_reps'] > 0
                    ? round($exercise['relative_volume'] / $exercise['intensity_reps'], 1)
                    : null;
                $exercise['relative_volume'] = round($exercise['relative_volume'], 2);
                $exercise['estimated_tonnage'] = round($exercise['estimated_tonnage'], 2);

                return $exercise;
            })
            ->values()
            ->all();
    }
}
