<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingSectionLiftingRow extends Model
{
    protected $fillable = [
        'exercise_block_id',
        'percentage',
        'reps',
        'sets',
        'rest_seconds',
        'notes',
        'order',
    ];

    protected $casts = [
        'percentage' => 'decimal:2',
        'reps' => 'integer',
        'sets' => 'integer',
        'rest_seconds' => 'integer',
    ];

    public function exerciseBlock(): BelongsTo
    {
        return $this->belongsTo(TrainingSectionExerciseBlock::class, 'exercise_block_id');
    }

    public function setLogs(): HasMany
    {
        return $this->hasMany(TrainingLiftingSetLog::class, 'lifting_row_id')
            ->orderBy('set_number');
    }
}
