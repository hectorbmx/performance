<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SectionTypeCatalog extends Model
{
    use SoftDeletes;

    protected $table = 'section_type_catalogs';

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

    // Relación opcional futura si agregas section_type_id a training_sections:
    // public function sections(): HasMany
    // {
    //     return $this->hasMany(TrainingSection::class, 'section_type_id');
    // }
}
