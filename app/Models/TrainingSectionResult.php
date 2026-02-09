<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingSectionResult extends Model
{
    protected $fillable = [
        'training_assignment_id',
        'training_section_id',
        'client_id',
        'result_type',
        'value_number',
        'value_time_seconds',
        'value_text',
        'value_bool',
        'value_json',
        'unit',
        'notes',
        'recorded_at',
    ];

    protected $casts = [
        'value_number' => 'float',
        'value_time_seconds' => 'integer',
        'value_bool' => 'boolean',
        'value_json' => 'array',
        'recorded_at' => 'datetime',
    ];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(TrainingAssignment::class, 'training_assignment_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(TrainingSection::class, 'training_section_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    /**
     * Normaliza el valor para la App (un solo campo).
     */
   public function normalizedValue(): mixed
{
    return match ($this->result_type) {
        'time'    => $this->value_time_seconds,
        'boolean' => $this->value_bool,
        'note'    => $this->value_text,
        'none'    => null,

        // Todos los numéricos
        'weight',
        'reps',
        'distance',
        'rounds',
        'sets',
        'calories',
        'points'  => $this->value_number,

        default   => null,
    };
}

}
