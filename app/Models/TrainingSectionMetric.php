<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingSectionMetric extends Model
{
    use SoftDeletes;

    protected $table = 'training_section_metrics';

    protected $fillable = [
        'coach_id',
        'training_section_id',
        'metric_id',
        'label',
        'required',
        'input_mode',     // single|repeatable
        'repeat_label',
        'unit_override',
        'sort_order',
    ];

    protected $casts = [
        'required'   => 'boolean',
        'sort_order' => 'integer',
    ];

    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    public function metric(): BelongsTo
    {
        return $this->belongsTo(MetricCatalog::class, 'metric_id');
    }

    /**
     * IMPORTANTE: No asumo tu modelo/namespace de TrainingSection.
     * Si tu modelo se llama diferente, lo ajustamos cuando me lo muestres.
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(TrainingSection::class, 'training_section_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(TrainingSubmissionValue::class, 'training_section_metric_id');
    }
}
