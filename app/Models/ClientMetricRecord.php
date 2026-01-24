<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientMetricRecord extends Model
{
    protected $table = 'client_metric_records';

    protected $fillable = [
        'client_id',
        'training_metric_id',
        'value',
        'recorded_at',
        'source',
        'notes',
    ];

    protected $casts = [
        'value'       => 'decimal:2',
        'recorded_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function trainingMetric()
    {
        return $this->belongsTo(TrainingMetric::class, 'training_metric_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeLatestFirst($query)
    {
        return $query->orderByDesc('recorded_at')->orderByDesc('id');
    }
}
