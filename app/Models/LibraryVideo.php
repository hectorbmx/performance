<?php

// app/Models/LibraryVideo.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LibraryVideo extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'coach_id',
        'training_type_catalog_id',
        'name',
        'youtube_url',
        'youtube_id',
        'thumbnail_url',
        'is_active',
    ];

    public function type()
    {
        return $this->belongsTo(TrainingTypeCatalog::class, 'training_type_catalog_id');
    }

    public function coach()
    {
        return $this->belongsTo(User::class, 'coach_id'); // ajusta si aplica
    }

    public function scopeVisibleForCoach($q, int $coachId)
    {
        return $q->where(function ($qq) use ($coachId) {
            $qq->whereNull('coach_id')
            ->orWhere('coach_id', $coachId);
        });
    }

    public function sections()
    {
        return $this->belongsToMany(\App\Models\TrainingSection::class, 'training_section_library_videos')
            ->withPivot(['order','notes'])
            ->withTimestamps()
            ->orderBy('training_section_library_videos.order');
    }

}
