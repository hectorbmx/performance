<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientDailyHealthMetric extends Model
{
    protected $fillable = [
        'client_id',
        'metric_date',
        'steps',
        'calories',
        'active_minutes',
        'source',
    ];

    protected $casts = [
        'metric_date' => 'date',
        'steps' => 'integer',
        'calories' => 'integer',
        'active_minutes' => 'integer',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
