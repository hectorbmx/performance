<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class WorkoutExecution extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'revision' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function getCurrentPrescriptionAttribute()
    {
        return WorkoutPrescriptionRevision::where('workout_execution_id',$this->id)->orderByDesc('execution_revision')->first() ?? $this->prescription;
    }

    public function prescription(): HasOne
    {
        return $this->hasOne(WorkoutExecutionPrescription::class);
    }
}
