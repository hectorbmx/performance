<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;




class TrainingSession extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'coach_id',
        'title',
        'scheduled_at',
        'duration_minutes',
        'level',
        'goal',
        'type',
        'training_type_catalog_id',
        'visibility',
        'notes',
        'tag_color',
        'cover_image',
    ];

    protected $casts = [
        'scheduled_at' => 'date',
    ];

    public function sections()
    {
        return $this->hasMany(TrainingSection::class)
            ->orderBy('order');
    }
    public function assignments()
    {
        return $this->hasMany(TrainingAssignment::class, 'training_session_id');
    }

    public function assignedClients()
    {
        return $this->belongsToMany(Client::class, 'training_assignments')
            ->withPivot('status')
            ->withTimestamps();
    }
    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(TrainingSubmission::class, 'training_session_id');
    }
    public function typeCatalog()
    {
        return $this->belongsTo(TrainingTypeCatalog::class, 'training_type_catalog_id');
    }
    public function clients()
        {
            return $this->assignments()->whereNotNull('client_id');
        }

        public function groups()
        {
            return $this->assignments()->whereNotNull('group_id');
        }
}
