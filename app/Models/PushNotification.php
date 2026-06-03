<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PushNotification extends Model
{
    protected $fillable = [
        'user_id',
        'device_id',
        'type',
        'title',
        'body',
        'data',
        'status',
        'provider',
        'provider_message_id',
        'error',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
    ];

    public function userApp()
    {
        return $this->belongsTo(UserApp::class, 'user_id');
    }

    public function device()
    {
        return $this->belongsTo(UserDevice::class, 'device_id');
    }
}
