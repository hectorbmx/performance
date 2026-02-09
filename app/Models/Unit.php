<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    protected $table = 'units';

    protected $fillable = [
        'coach_id',
        'code',
        'name',
        'symbol',
        'result_type',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Coach dueño de la unidad (null = global)
     */
    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    /**
     * Secciones que usan esta unidad
     */
    public function sections(): HasMany
    {
        return $this->hasMany(TrainingSection::class, 'unit_id');
    }

    /**
     * Scope: unidades visibles para un coach
     * (globales + propias)
     */
    public function scopeVisibleToCoach($query, int $coachId)
    {
        return $query
            ->whereNull('coach_id')
            ->orWhere('coach_id', $coachId);
    }

    /**
     * Scope: filtrar por tipo de resultado
     */
    public function scopeForResultType($query, string $type)
    {
        return $query->where('result_type', $type);
    }
}
