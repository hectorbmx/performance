<?php

namespace App\Models;

use App\Enums\TipCategory;
use App\Enums\TipScope;
use App\Enums\TipStatus;
use App\Enums\TipType;
use App\Support\Tips\TipContentRules;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class Tip extends Model
{
    // Only editorial fields may come from a form. Ownership and lifecycle are server-owned.
    protected $fillable = ['title', 'body', 'type', 'category', 'expires_at'];

    protected $attributes = [
        'type' => 'tip',
        'category' => 'general',
        'status' => 'draft',
    ];

    protected $casts = [
        'author_id' => 'integer',
        'coach_id' => 'integer',
        'reviewed_by' => 'integer',
        'type' => TipType::class,
        'category' => TipCategory::class,
        'scope' => TipScope::class,
        'status' => TipStatus::class,
        'reviewed_at' => 'datetime',
        'published_at' => 'datetime',
        'expires_at' => 'datetime',
        'archived_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $tip): void {
            // Persistence invariants, not actor permissions or workflow transitions (CP2).
            Validator::make($tip->getAttributes(), array_merge(TipContentRules::rules(), [
                'author_id' => ['required', 'integer', 'min:1'],
                'coach_id' => ['nullable', 'integer', 'min:1'],
                'scope' => ['required', Rule::in(TipScope::values())],
                'status' => ['required', Rule::in(TipStatus::values())],
                'expires_at' => ['nullable', 'date'],
                'rejection_reason' => ['nullable', 'string', 'max:2000'],
            ]))->validate();

            if ($tip->exists && $tip->isDirty(['author_id', 'coach_id', 'scope'])) {
                throw ValidationException::withMessages(['scope' => 'La autoría y el alcance no se pueden modificar.']);
            }

            if (($tip->scope === TipScope::GLOBAL && $tip->coach_id !== null)
                || ($tip->scope === TipScope::TENANT && $tip->coach_id !== $tip->author_id)) {
                throw ValidationException::withMessages(['scope' => 'El coach asociado no corresponde al alcance y autor de la publicación.']);
            }
        });
    }

    public function setTitleAttribute($value): void
    {
        $this->attributes['title'] = is_string($value) ? trim($value) : $value;
    }

    public function setBodyAttribute($value): void
    {
        $this->attributes['body'] = is_string($value) ? trim($value) : $value;
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
