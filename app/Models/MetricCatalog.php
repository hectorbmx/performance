<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MetricCatalog extends Model
{
    use SoftDeletes;

    protected $table = 'metric_catalogs';

    protected $fillable = [
        'coach_id',
        'name',
        'value_kind',     // duration|integer|decimal|boolean|text
        'unit_default',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    public function sectionMetrics(): HasMany
    {
        return $this->hasMany(TrainingSectionMetric::class, 'metric_id');
    }
}
