<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class WorkoutExercise extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['coach_id' => 'integer', 'variant_of_id' => 'integer', 'names' => 'array', 'unilateral' => 'boolean', 'is_active' => 'boolean'];
    public function terms() { return $this->belongsToMany(WorkoutTaxonomyTerm::class, 'workout_exercise_terms')->withPivot('role'); }
    public function variantOf() { return $this->belongsTo(self::class, 'variant_of_id'); }
    public function scopeVisibleForCoach($query, int $coachId) {
        return $query->where(fn ($q) => $q->whereNull('coach_id')->orWhere('coach_id', $coachId));
    }
    protected static function booted(): void {
        static::updating(function ($exercise) {
            if ($exercise->isDirty(['code', 'coach_id', 'variant_of_id'])) throw new LogicException('La identidad y origen de un ejercicio son inmutables.');
        });
    }
}
