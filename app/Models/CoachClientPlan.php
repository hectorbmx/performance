<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CoachClientPlan extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'coach_id',
        'name',
        'description',
        'price',
        'currency',
        'billing_cycle_days',
        'reminder_days_before',
        'grace_days',
        'status',
        'stripe_product_id',
        'stripe_price_id',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'billing_cycle_days' => 'integer',
        'reminder_days_before' => 'integer',
        'grace_days' => 'integer',
    ];

    // Relaciones
    public function coach()
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    public function clientMemberships()
    {
        return $this->hasMany(ClientMembership::class, 'coach_client_plan_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeInactive($query)
    {
        return $query->where('status', 'inactive');
    }

    // Accessors
    public function getIsActiveAttribute(): bool
    {
        return $this->status === 'active';
    }
}
