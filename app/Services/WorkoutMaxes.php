<?php
namespace App\Services;

use App\Models\Client;
use App\Models\UserApp;
use App\Models\WorkoutExercise;
use App\Models\WorkoutExecution;
use App\Models\WorkoutMaxRecord;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class WorkoutMaxes
{
    public function micros($value): int {
        $parts = explode('.', (string) $value, 2);
        return (int) $parts[0] * 1000000 + (int) str_pad(substr($parts[1] ?? '', 0, 6), 6, '0');
    }
    public function decimal(int $micros): string {
        return intdiv($micros, 1000000).'.'.str_pad((string) ($micros % 1000000), 6, '0', STR_PAD_LEFT);
    }
    public function kg($value, string $unit): string {
        $parts = explode('.', (string) $value, 2); $cents = (int) $parts[0] * 100 + (int) str_pad($parts[1] ?? '', 2, '0');
        return $this->decimal($unit === 'kg' ? $cents * 10000 : intdiv($cents * 45359237 + 5000, 10000));
    }
    public function records(Client|UserApp $client, WorkoutExercise $exercise) {
        return app(WorkoutOwnership::class)->data(WorkoutMaxRecord::query(),$client)->where('workout_exercise_id', $exercise->id)
            ->orderByDesc('measured_at')->orderByDesc('id');
    }
    public function history(Client|UserApp $client, WorkoutExercise $exercise): array {
        $rows = app(WorkoutOwnership::class)->data(DB::table('workout_execution_sets as sets')->join('workout_executions as executions', 'executions.id', '=', 'sets.workout_execution_id'),$client,'executions.')
            ->when($client instanceof Client,fn($q)=>$q->where('executions.data_scope',WorkoutOwnership::ASSIGNED))
            ->where('sets.type', 'work')
            ->where('sets.performed_at', '>=', now()->subDays(config('workout_prescription.max_history_age_days')))
            ->where('sets.performed_at', '<=', now())->orderByDesc('sets.performed_at')->orderByDesc('sets.id')
            ->limit(200)->get(['sets.*', 'executions.uuid as execution_uuid']);
        $result = []; $snapshots = [];
        foreach ($rows as $row) {
            $snapshot = $snapshots[$row->workout_execution_id] ??= WorkoutExecution::find($row->workout_execution_id)->current_prescription->content;
            $matches = false;
            foreach ($snapshot['sections'] as $section) foreach ($section['exercises'] as $item) {
                if ((int) ($item['workout_exercise_id'] ?? 0) !== (int) $exercise->id) continue;
                foreach ($item['sets'] as $set) if ($set['uuid'] === $row->set_uuid) $matches = true;
            }
            if ($matches && $row->load_mode === 'total_barbell') $result[] = $row;
        }
        return $result;
    }
    public function estimate(object $set): ?array {
        $rules = config('workout_prescription');
        if ($set->status !== 'completed' || $set->type !== 'work' || $set->load_kg === null || $set->actual_rir === null) return null;
        $rirTenths = (int) round((float) $set->actual_rir * 10);
        $effectiveTenths = (int) $set->actual_reps * 10 + $rirTenths;
        if ($set->actual_reps < 1 || $rirTenths > $rules['estimate_max_rir'] * 10 || $effectiveTenths > $rules['estimate_max_effective_reps'] * 10) return null;
        $micros = $this->micros($set->load_kg); if ($micros <= 0) return null;
        $estimated = $effectiveTenths === 10 ? $micros : intdiv($micros * (300 + $effectiveTenths) + 150, 300);
        return ['value_kg' => $this->decimal($estimated), 'kind' => 'estimated', 'method' => 'epley_rir_v1', 'confidence' => 'low',
            'source' => 'recorded_set', 'source_set_id' => $set->id, 'source_revision' => $set->revision, 'measured_at' => Carbon::parse($set->performed_at,'UTC')->toIso8601String()];
    }
    public function basis(Client|UserApp $client, WorkoutExercise $exercise): ?array {
        $records = $this->records($client, $exercise)->where('measured_at', '>=', now()->subDays(config('workout_prescription.max_rm_age_days')))
            ->where('measured_at', '<=', now())->whereNotIn('id', app(WorkoutOwnership::class)->data(WorkoutMaxRecord::whereNotNull('supersedes_id'),$client)->select('supersedes_id'))->get();
        // Declared single-rep tests remain distinct and take precedence while fresh.
        foreach (['tested', 'estimated'] as $kind) foreach ($records->where('kind', $kind) as $record) {
            if ($record->source_set_id) {
                $current = DB::table('workout_execution_sets')->find($record->source_set_id);
                if (!$current || (int) $current->revision !== (int) $record->source_revision) continue;
            }
            return ['record_id' => $record->id, 'kind' => $record->kind, 'value_kg' => $record->value_kg, 'method' => $record->method,
                'confidence' => $record->confidence, 'source' => $record->source, 'measured_at' => $record->measured_at->toIso8601String()];
        }
        foreach ($this->history($client, $exercise) as $set) if ($estimate = $this->estimate($set)) return $estimate;
        return null;
    }
}
