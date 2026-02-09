<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'coach_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'is_active',
    ];

    public function coach()
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    public function getFullNameAttribute(): string
    {
        return trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
    }
    // Agregar esta relación al modelo Client existente

    public function membership()
    {
        return $this->hasOne(ClientMembership::class);
    }

    // O si puede tener historial de membresías:
    public function memberships()
    {
        return $this->hasMany(ClientMembership::class);
    }

    public function activeMembership()
        {
            return $this->hasOne(ClientMembership::class)
                ->where('status', 'active')
                ->whereDate('ends_at', '>=', now()->toDateString())
                ->latestOfMany('starts_at'); // por si hubiera más de una
        }

        public function latestMembership()
    {
        return $this->hasOne(ClientMembership::class)->latestOfMany('starts_at');
        // o ->latestOfMany('ends_at');
    }

    // Agregar esta relación al modelo Client

    public function payments()
    {
        return $this->hasMany(ClientPayment::class);
    }
    public function groups()
    {
        return $this->belongsToMany(\App\Models\Group::class, 'client_group')
            ->withTimestamps();
    }
    public function userApp()
    {
        return $this->hasOne(\App\Models\UserApp::class, 'client_id');
    }

    // NUEVO: Perfil extendido
    public function profile()
    {
        return $this->hasOne(\App\Models\ClientProfile::class, 'client_id');
    }

    // NUEVO: Historial de métricas (PRs, 1RM, etc.)
    public function metricRecords()
    {
        return $this->hasMany(\App\Models\ClientMetricRecord::class, 'client_id');
    }

    // NUEVO: Historial corporal (peso, etc.)
    public function bodyRecords()
    {
        return $this->hasMany(\App\Models\ClientBodyRecord::class, 'client_id');
    }

    public function healthProfile()
    {
        return $this->hasOne(\App\Models\ClientHealthProfile::class, 'client_id');
    }
    
    public function latestBodyRecord()
    {
        return $this->hasOne(\App\Models\ClientBodyRecord::class, 'client_id')
            ->latest('recorded_at')
            ->latest('id');
    }

}
