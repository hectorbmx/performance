<?php

namespace App\Services;

use App\Models\UserApp;
use App\Models\Client;
use App\Models\WorkoutExecution;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class WorkoutSetRecords
{
    public function save(UserApp $actor, string $executionUuid, string $setUuid, array $data): array
    {
        $setUuid = strtolower($setUuid);
        $operationId = strtolower($data['operation_id']);
        $values = [
            'status' => $data['status'], 'type' => $data['type'], 'load_mode' => $data['load_mode'],
            'actual_reps' => $data['actual_reps'] === null ? null : (int) $data['actual_reps'],
            'actual_load' => $data['actual_load'] === null ? null : $this->decimal($data['actual_load'], 2),
            'load_unit' => $data['load_unit'],
            'actual_rir' => $data['actual_rir'] === null ? null : $this->decimal($data['actual_rir'], 1),
            'notes' => trim($data['notes'] ?? '') === '' ? null : trim($data['notes']),
            'performed_at' => Carbon::parse($data['performed_at'])->utc()->format('Y-m-d H:i:s'),
        ];
        $values['load_kg'] = $values['actual_load'] === null ? null : $this->kilograms($values['actual_load'], $values['load_unit']);
        $hash = hash('sha256', json_encode([
            'execution' => strtolower($executionUuid), 'set' => $setUuid,
            'expected_revision' => (int) $data['expected_revision'], 'values' => $values,
        ], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($actor, $executionUuid, $setUuid, $operationId, $data, $values, $hash) {
            $ownership=app(WorkoutOwnership::class);$ownership->lock($actor);
            $execution = $ownership->executions($actor)->where('uuid', $executionUuid)->lockForUpdate()->firstOrFail();
            // Replays are authorized again and return their original result, even after later edits.
            $operation = DB::table('workout_sync_operations')->where('user_app_id', $actor->id)
                ->where('operation_id', $operationId)->lockForUpdate()->first();
            if ($operation) {
                if (!hash_equals($operation->payload_hash, $hash)) {
                    return ['ok' => false, 'code' => 'operation_reused', 'message' => 'La operaciÃƒÂ³n ya se usÃƒÂ³ con otros datos.', '_status' => 409];
                }
                return json_decode($operation->response, true, 512, JSON_THROW_ON_ERROR);
            }
            abort_unless($execution->status === 'in_progress', 422, 'La ejecuciÃƒÂ³n no permite nuevos registros.');
            $found = false;
            foreach ($execution->current_prescription->content['sections'] as $section) {
                foreach ($section['exercises'] as $exercise) {
                    foreach ($exercise['sets'] as $set) {
                        if ($set['uuid'] === $setUuid) {
                            $found = true;
                            $catalogMode = $exercise['catalog']['load_mode'] ?? $exercise['load_mode'] ?? null;
                            abort_if($values['actual_load'] !== null && ($catalogMode === 'bodyweight' ? 'external_total' : ($catalogMode ?? 'total_barbell')) !== $values['load_mode'],422,
                                'La modalidad de carga no coincide con el ejercicio.');
                        }
                    }
                }
            }
            abort_unless($found, 404, 'Serie no encontrada en esta ejecuciÃƒÂ³n.');
            $query = DB::table('workout_execution_sets')->where('workout_execution_id', $execution->id)->where('set_uuid', $setUuid);
            $existing = (clone $query)->lockForUpdate()->first();
            $revision = $existing ? (int) $existing->revision : 0;
            if ($revision !== (int) $data['expected_revision']) {
                return ['ok' => false, 'code' => 'revision_conflict', 'message' => 'La serie cambiÃƒÂ³ en otro dispositivo.',
                    'data' => ['current' => $existing ? $this->payload($existing) : null, 'current_revision' => $revision], '_status' => 409];
            }
            $before = $existing ? $this->payload($existing) : null;
            $stored = $values + ['received_at' => now()->utc()->format('Y-m-d H:i:s'), 'revision' => $revision + 1];
            if ($existing) {
                (clone $query)->update($stored);
                $id = $existing->id;
            } else {
                $id = DB::table('workout_execution_sets')->insertGetId($stored + ['workout_execution_id' => $execution->id, 'set_uuid' => $setUuid]);
            }
            $execution->increment('revision');
            $after = $this->payload(DB::table('workout_execution_sets')->find($id));
            $response = ['ok' => true, 'data' => ['operation_id' => $operationId, 'execution_uuid' => $execution->uuid,
                'execution_revision' => $execution->revision, 'set' => $after]];
            $operationDbId = DB::table('workout_sync_operations')->insertGetId([
                'client_id' => $execution->client_id, 'user_app_id' => $actor->id, 'workout_execution_id' => $execution->id,
                'operation_id' => $operationId, 'payload_hash' => $hash,
                'response' => json_encode($response, JSON_THROW_ON_ERROR), 'created_at' => now()->utc(),
            ]);
            DB::table('workout_set_revisions')->insert([
                'workout_execution_set_id' => $id, 'workout_sync_operation_id' => $operationDbId,
                'revision' => $revision + 1, 'before' => $before ? json_encode($before, JSON_THROW_ON_ERROR) : null,
                'after' => json_encode($after, JSON_THROW_ON_ERROR), 'created_at' => now()->utc(),
            ]);
            return $response;
        }, 3);
    }

    public function forExecution(WorkoutExecution $execution): array
    {
        return DB::table('workout_execution_sets')->where('workout_execution_id', $execution->id)
            ->orderBy('id')->get()->map(fn ($row) => $this->payload($row))->all();
    }

    public function revisions(WorkoutExecution $execution, string $setUuid): array
    {
        $set = DB::table('workout_execution_sets')->where('workout_execution_id', $execution->id)->where('set_uuid', strtolower($setUuid))->first();
        abort_unless($set, 404);
        return DB::table('workout_set_revisions as revisions')
            ->join('workout_sync_operations as operations', 'operations.id', '=', 'revisions.workout_sync_operation_id')
            ->where('revisions.workout_execution_set_id', $set->id)->orderBy('revisions.revision')
            ->get(['revisions.*', 'operations.operation_id', 'operations.user_app_id'])->map(fn ($row) => [
                'revision' => (int) $row->revision, 'operation_id' => $row->operation_id,
                'actor_id' => (int) $row->user_app_id,
                'before' => $row->before === null ? null : json_decode($row->before, true, 512, JSON_THROW_ON_ERROR),
                'after' => json_decode($row->after, true, 512, JSON_THROW_ON_ERROR),
                'created_at' => Carbon::parse($row->created_at, 'UTC')->toIso8601String(),
            ])->all();
    }

    private function payload(object $row): array
    {
        return [
            'set_uuid' => $row->set_uuid, 'status' => $row->status, 'type' => $row->type, 'load_mode' => $row->load_mode,
            'actual_reps' => $row->actual_reps === null ? null : (int) $row->actual_reps,
            'actual_load' => $row->actual_load === null ? null : $this->decimal($row->actual_load, 2),
            'load_unit' => $row->load_unit,
            'load_kg' => $row->load_kg === null ? null : $this->decimal($row->load_kg, 6),
            'actual_rir' => $row->actual_rir === null ? null : $this->decimal($row->actual_rir, 1),
            'notes' => $row->notes, 'revision' => (int) $row->revision,
            'performed_at' => Carbon::parse($row->performed_at, 'UTC')->toIso8601String(),
            'received_at' => Carbon::parse($row->received_at, 'UTC')->toIso8601String(),
        ];
    }

    private function decimal($value, int $places): string
    {
        // Input has already been validated; pad decimals without binary-float arithmetic.
        $parts = explode('.', (string) $value, 2);
        return (ltrim($parts[0], '0') ?: '0').'.'.str_pad(substr($parts[1] ?? '', 0, $places), $places, '0');
    }

    private function kilograms(string $load, string $unit): string
    {
        $cents = (int) str_replace('.', '', $load);
        $micros = $unit === 'kg' ? $cents * 10000 : intdiv($cents * 45359237 + 5000, 10000);
        return intdiv($micros, 1000000).'.'.str_pad((string) ($micros % 1000000), 6, '0', STR_PAD_LEFT);
    }
}
