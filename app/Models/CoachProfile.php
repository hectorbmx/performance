<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CoachProfile extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'coach_profiles';

    protected $fillable = [
        'user_id',
        'display_name',
        'phone',
        'status',
        'suspended_at',
        'suspension_reason',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'suspended_at' => 'datetime',
    ];

    // --- Relaciones ---

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
