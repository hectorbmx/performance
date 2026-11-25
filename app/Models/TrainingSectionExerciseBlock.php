<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingSectionExerciseBlock extends Model
{
    protected $fillable = [
        'training_section_id',
        'exercise_catalog_id',
        'workout_exercise_id',
        'exercise_name',
        'notes',
        'order',
    ];

    public function workoutExercise(): BelongsTo
    {
        return $this->belongsTo(WorkoutExercise::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(TrainingSection::class, 'training_section_id');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(TrainingSectionLiftingRow::class, 'exercise_block_id')
            ->orderBy('order');
    }
}
