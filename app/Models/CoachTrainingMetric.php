<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoachTrainingMetric extends Model
{
    protected $table = 'coach_training_metrics';

    protected $fillable = [
        'coach_id',
        'training_metric_id',
        'is_required',
        'sort_order',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'sort_order'  => 'integer',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    // Coach = User
    public function coach()
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    // Métrica configurada
    public function metric()
    {
        return $this->belongsTo(TrainingMetric::class, 'training_metric_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }
}
