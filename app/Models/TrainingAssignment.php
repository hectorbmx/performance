<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingAssignment extends Model
{
    protected $fillable = [
        'training_session_id',
        'client_id',
        'scheduled_for',
        'status',
    ];
    protected $casts = [
    'scheduled_for' => 'date:Y-m-d',
];


    public function training()
    {
        return $this->belongsTo(TrainingSession::class, 'training_session_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
    public function sectionResults(): HasMany
    {
        return $this->hasMany(TrainingSectionResult::class, 'training_assignment_id');
    }
    public function trainingSession()
    {
            return $this->belongsTo(TrainingSession::class);
        }

}
