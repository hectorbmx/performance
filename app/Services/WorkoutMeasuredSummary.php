<?php

namespace App\Services;

class WorkoutMeasuredSummary
{
    public function summarize(array $snapshot, array $records): array
    {
        $logs = collect($records)->keyBy('set_uuid');
        $total = $this->empty(); $exercises = []; $muscles = []; $unclassified = 0;
        foreach ($snapshot['sections'] ?? [] as $section) {
            foreach ($section['exercises'] ?? [] as $exercise) {
                $summary = $this->empty();
                foreach ($exercise['sets'] ?? [] as $set) {
                    $summary['sets_prescribed']++;
                    $log = $logs->get($set['uuid']);
                    if (!$log) { $summary['sets_unrecorded']++; continue; }
                    $summary['sets_registered']++;
                    $status = $log['status'];
                    $summary['sets_'.$status]++;
                    if ($status === 'completed' && $log['type'] === 'work') $summary['work_sets_completed']++;
                    if ($status === 'skipped') continue;
                    $reps = (int) ($log['actual_reps'] ?? 0);
                    $summary['reps_performed'] += $reps;
                    if ($reps <= 0) continue;
                    if (($log['load_mode'] ?? null) !== 'total_barbell' || ($log['load_kg'] ?? null) === null) {
                        $summary['sets_without_load']++; continue;
                    }
                    $parts = explode('.', (string) $log['load_kg'], 2);
                    $micros = (int) $parts[0] * 1000000 + (int) str_pad($parts[1] ?? '', 6, '0');
                    $summary['sets_with_measured_load']++;
                    $summary['tonnage_micros'] += $micros * $reps;
                    $summary[$log['type'] === 'warmup' ? 'warmup_tonnage_micros' : 'work_tonnage_micros'] += $micros * $reps;
                }
                $primary = array_values(array_unique(array_column(array_filter($exercise['catalog']['terms'] ?? [],
                    fn($term)=>$term['kind']==='muscle' && $term['role']==='primary'),'code')));
                if (!$primary) $unclassified += $summary['work_sets_completed'];
                foreach ($primary as $code) $muscles[$code] = ($muscles[$code] ?? 0) + $summary['work_sets_completed'];
                foreach (array_keys($total) as $key) $total[$key] += $summary[$key];
                $exercises[] = [
                    'exercise_uuid' => $exercise['uuid'], 'workout_exercise_id' => $exercise['workout_exercise_id'] ?? null,
                    'exercise_name' => $exercise['name'], 'summary' => $this->finish($summary),
                ];
            }
        }
        return $this->finish($total) + ['exercises' => $exercises,
            'exercises_completed' => collect($exercises)->filter(fn ($item) => $item['summary']['sets_prescribed'] > 0
                && $item['summary']['sets_completed'] === $item['summary']['sets_prescribed'])->count(),
            'source' => 'recorded_sets', 'unit' => 'kg', 'muscle_series_available' => count($muscles)>0, 'direct_muscle_series' => $muscles,
            'unclassified_work_sets' => $unclassified, 'muscle_counting' => 'completed_work_sets_per_frozen_primary_muscle'];
    }

    private function empty(): array
    {
        return array_fill_keys(['sets_prescribed', 'sets_registered', 'sets_unrecorded', 'sets_completed', 'sets_failed', 'sets_skipped',
            'work_sets_completed', 'reps_performed', 'sets_without_load', 'sets_with_measured_load', 'tonnage_micros', 'work_tonnage_micros', 'warmup_tonnage_micros'], 0);
    }

    private function finish(array $summary): array
    {
        foreach (['tonnage', 'work_tonnage', 'warmup_tonnage'] as $key) {
            $micros = $summary[$key.'_micros']; unset($summary[$key.'_micros']);
            $summary[$key.'_kg'] = $summary['sets_with_measured_load'] === 0 ? null
                : intdiv($micros, 1000000).'.'.str_pad((string) ($micros % 1000000), 6, '0', STR_PAD_LEFT);
        }
        $summary['series_adherence_percent'] = $summary['sets_prescribed'] > 0 ? round(100 * $summary['sets_completed'] / $summary['sets_prescribed'],1) : null;
        $summary['tonnage_complete'] = $summary['sets_without_load'] === 0 && $summary['sets_unrecorded'] === 0;
        return $summary;
    }
}
