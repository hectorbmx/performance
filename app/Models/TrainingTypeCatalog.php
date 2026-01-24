<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingTypeCatalog extends Model
{
    use SoftDeletes;

    protected $table = 'training_type_catalogs';

    protected $fillable = [
        'coach_id',
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    // Relación opcional futura (no asumo tu modelo TrainingSession):
    // public function trainingSessions(): HasMany
    // {
    //     return $this->hasMany(TrainingSession::class, 'training_type_id');
    // }
        public function trainingSessions()
    {
        return $this->hasMany(TrainingSession::class, 'training_type_catalog_id');
    }
}
