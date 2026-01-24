<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrainingMetric extends Model
{
    protected $table = 'training_metrics';

    protected $fillable = [
        'coach_id',
        'code',
        'name',
        'unit',
        'type',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    // Configuración por coach (pivot)
    public function coachConfigs()
    {
        return $this->hasMany(CoachTrainingMetric::class, 'training_metric_id');
    }

    // Historial de valores por atleta
    public function clientRecords()
    {
        return $this->hasMany(ClientMetricRecord::class, 'training_metric_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
    public function coach()
        {
            return $this->belongsTo(User::class, 'coach_id');
        }

        public function scopeVisibleToCoach($query, int $coachId)
        {
            return $query->whereNull('coach_id')
                ->orWhere('coach_id', $coachId);
        }

}

