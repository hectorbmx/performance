<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class UserApp extends Authenticatable
{
    use HasApiTokens, SoftDeletes;

    protected $table = 'user_apps'; // ← Cambiar a user_apps

    protected $fillable = [
        'client_id',
        'email',
        'password',
        'is_active',
        'activation_code',
        'activation_expires_at',
        'activated_at',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'activation_code',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'activation_expires_at' => 'datetime',
        'activated_at' => 'datetime',
        'last_login_at' => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }
    public function devices()
    {
        return $this->hasMany(UserDevice::class, 'user_id');
    }
}