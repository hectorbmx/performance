<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientMembership extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'coach_id',
        'client_id',
        'coach_client_plan_id',
        'plan_name_snapshot',
        'price_snapshot',
        'billing_cycle_days_snapshot',
        'starts_at',
        'ends_at',
        'next_renewal_at',
        'reminder_days_before',
        'status',
        'billing_status',
        'grace_until',
        'paid_at',
    ];

    protected $casts = [
        'price_snapshot' => 'decimal:2',
        'billing_cycle_days_snapshot' => 'integer',
        'reminder_days_before' => 'integer',
        'starts_at' => 'date',
        'ends_at' => 'date',
        'next_renewal_at' => 'date',
        'grace_until' => 'date',
        'paid_at' => 'date',
    ];

    // Relaciones
    public function coach()
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class,'client_id');
    }

    public function coachClientPlan()
    {
        return $this->belongsTo(CoachClientPlan::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopePaid($query)
    {
        return $query->where('billing_status', 'paid');
    }

    public function scopeUnpaid($query)
    {
        return $query->where('billing_status', 'unpaid');
    }

    public function scopeInGrace($query)
    {
        return $query->where('billing_status', 'unpaid')
            ->whereNotNull('grace_until')
            ->where('grace_until', '>=', now()->startOfDay());
    }

    public function scopeGraceExpired($query)
    {
        return $query->where('billing_status', 'unpaid')
            ->where(function ($q) {
                $q->whereNull('grace_until')
                  ->orWhere('grace_until', '<', now()->startOfDay());
            });
    }

    // Accessors
    public function getIsActiveAttribute(): bool
    {
        return $this->status === 'active';
    }

    public function getIsPaidAttribute(): bool
    {
        return $this->billing_status === 'paid';
    }

    public function getIsInGraceAttribute(): bool
    {
        return $this->billing_status === 'unpaid'
            && $this->grace_until
            && now()->startOfDay()->lte($this->grace_until->startOfDay());
    }
    // Agregar esta relación al modelo ClientMembership

    public function payments()
    {
        return $this->hasMany(ClientPayment::class,'client_membership_id');
    }
}