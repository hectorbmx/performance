<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingLiftingSetLog extends Model
{
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'training_assignment_id',
        'lifting_row_id',
        'set_number',
        'status',
        'actual_reps',
        'failure_reason',
        'notes',
        'logged_at',
    ];

    protected $casts = [
        'set_number' => 'integer',
        'actual_reps' => 'integer',
        'logged_at' => 'datetime',
    ];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(TrainingAssignment::class, 'training_assignment_id');
    }

    public function liftingRow(): BelongsTo
    {
        return $this->belongsTo(TrainingSectionLiftingRow::class, 'lifting_row_id');
    }
}
