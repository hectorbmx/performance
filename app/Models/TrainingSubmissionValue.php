<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingSubmissionValue extends Model
{
    protected $table = 'training_submission_values';

    protected $fillable = [
        'coach_id',
        'training_submission_id',
        'training_section_metric_id',
        'item_index',
        'value_duration_seconds',
        'value_int',
        'value_decimal',
        'value_text',
        'value_bool',
    ];

    protected $casts = [
        'item_index' => 'integer',
        'value_duration_seconds' => 'integer',
        'value_int' => 'integer',
        'value_decimal' => 'decimal:3',
        'value_bool' => 'boolean',
    ];

    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(TrainingSubmission::class, 'training_submission_id');
    }

    public function sectionMetric(): BelongsTo
    {
        return $this->belongsTo(TrainingSectionMetric::class, 'training_section_metric_id');
    }
}
