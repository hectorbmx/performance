<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientProfile extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'client_profiles';

    protected $fillable = [
        'user_id',
        'coach_id',
        'display_name',
        'phone',
    ];

    // --- Relaciones ---

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Coach (tenant owner)
    public function coach()
    {
        return $this->belongsTo(User::class, 'coach_id');
    }
}
