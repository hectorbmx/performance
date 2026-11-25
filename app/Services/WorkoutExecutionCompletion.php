<?php

namespace App\Services;

use App\Models\Client;
use App\Models\UserApp;
use App\Models\WorkoutExecution;
use Illuminate\Support\Facades\DB;

class WorkoutExecutionCompletion
{
    public function complete(UserApp $actor, string $uuid, array $data): array
    {
        $operationId = strtolower($data['operation_id']);
        $hash = hash('sha256', json_encode([
            'action' => 'complete', 'execution' => strtolower($uuid),
            'expected_execution_revision' => (int) $data['expected_execution_revision'],
        ], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($actor, $uuid, $data, $operationId, $hash) {
            $ownership=app(WorkoutOwnership::class);$ownership->lock($actor);
            $execution = $ownership->executions($actor)->where('uuid',$uuid)->lockForUpdate()->firstOrFail();
            $operation = DB::table('workout_sync_operations')->where('user_app_id', $actor->id)
                ->where('operation_id', $operationId)->lockForUpdate()->first();
            if ($operation) {
                if (!hash_equals($operation->payload_hash, $hash)) {
                    return ['ok' => false, 'code' => 'operation_reused', 'message' => 'La operación ya se usó con otros datos.', '_status' => 409];
                }
                return json_decode($operation->response, true, 512, JSON_THROW_ON_ERROR);
            }
            if ($execution->revision !== (int) $data['expected_execution_revision'] || $execution->status !== 'in_progress') {
                return ['ok' => false, 'code' => 'execution_conflict', 'message' => 'La sesión cambió. Revisa sus registros antes de finalizar.',
                    'data' => ['revision' => $execution->revision, 'status' => $execution->status], '_status' => 409];
            }
            // Completion is voluntary: untouched or skipped series never become completed.
            $execution->update(['status' => 'completed', 'completed_at' => now(), 'revision' => $execution->revision + 1]);
            $response = ['ok' => true, 'data' => [
                'operation_id' => $operationId, 'execution_uuid' => $execution->uuid,
                'status' => $execution->status, 'execution_revision' => $execution->revision,
                'completed_at' => $execution->completed_at->toIso8601String(),
            ]];
            DB::table('workout_sync_operations')->insert([
                'client_id' => $execution->client_id, 'user_app_id' => $actor->id, 'workout_execution_id' => $execution->id,
                'operation_id' => $operationId, 'payload_hash' => $hash,
                'response' => json_encode($response, JSON_THROW_ON_ERROR), 'created_at' => now()->utc(),
            ]);
            return $response;
        }, 3);
    }
}
