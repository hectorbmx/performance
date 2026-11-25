<?php
namespace App\Services;
use App\Models\WorkoutExecution;
class WorkoutExecutionPresenter {
    public function header(WorkoutExecution $execution): array {
        return ['uuid' => $execution->uuid, 'client_execution_id' => $execution->client_execution_id,
            'ownership_scope'=>$execution->data_scope,
            'status' => $execution->status, 'revision' => $execution->revision, 'started_at' => $execution->started_at->toIso8601String(),
            'completed_at' => $execution->completed_at?->toIso8601String()];
    }
    public function detail(WorkoutExecution $execution): array {
        $prescription = $execution->current_prescription; $records = app(WorkoutSetRecords::class)->forExecution($execution);
        return $this->header($execution) + ['sets' => $records,
            'summary' => app(WorkoutMeasuredSummary::class)->summarize($prescription->content, $records),
            'prescription' => ['schema_version' => $prescription->schema_version, 'content_hash' => $prescription->content_hash, 'content' => $prescription->content]];
    }
}
