<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserDevice extends Model
{
    use HasFactory;

    protected $table = 'user_devices';

    protected $fillable = [
        'user_id',
        'platform',
        'token',
        'is_enabled',
        'last_seen_at',
        'device_name',
        'device_model',
        'app_version',
    ];

    protected $casts = [
        'is_enabled'   => 'boolean',
        'last_seen_at'=> 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function userApp()
    {
        // Apuntamos a UserApp porque ellos son los que tienen el dispositivo
        return $this->belongsTo(UserApp::class, 'user_id');
    }
}
