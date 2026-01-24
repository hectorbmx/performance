<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;



class TrainingSection extends Model
{
    protected $fillable = [
        'training_session_id',
        'order',
        'name',
        'description',
        'video_url',
        'accepts_results',
        'result_type',
    ];

    protected $casts = [
        'accepts_results' => 'boolean',
    ];

    public function training()
    {
        return $this->belongsTo(TrainingSession::class);
    }
    public function results(): HasMany
    {
        return $this->hasMany(TrainingSectionResult::class, 'training_section_id');
    }
    
}
