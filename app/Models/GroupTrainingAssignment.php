<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupTrainingAssignment extends Model
{
    protected $fillable = [
        'group_id', 
        'training_session_id', 
        'scheduled_for', 
        'notes'
    ];

    protected $casts = [
        'scheduled_for' => 'date',
    ];

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

  
    public function trainingSession()
    {
        return $this->belongsTo(TrainingSession::class, 'training_session_id');
    }
}
