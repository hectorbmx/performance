<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingSubmission extends Model
{
    use SoftDeletes;

    protected $table = 'training_submissions';

    protected $fillable = [
        'coach_id',
        'training_session_id',
        'client_id',
        'submitted_at',
        'notes',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
    ];

    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    /**
     * IMPORTANTE: No asumo tu modelo/namespace de TrainingSession.
     * Si tu modelo se llama diferente, lo ajustamos cuando me lo muestres.
     */
    public function trainingSession(): BelongsTo
    {
        return $this->belongsTo(TrainingSession::class, 'training_session_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(TrainingSubmissionValue::class, 'training_submission_id');
    }
}
