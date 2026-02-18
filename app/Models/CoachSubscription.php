<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CoachSubscription extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'coach_subscriptions';

    protected $fillable = [
        'coach_id',
        'membership_plan_id',
        'plan_name_snapshot',
        'billing_cycle_days_snapshot',
        'client_limit_snapshot',
        'starts_at',
        'ends_at',
        'next_renewal_at',
        'reminder_days_before',
        'billing_status',
        'grace_until',
        'paid_at',
        'stripe_subscription_id',
        'stripe_status',
        'stripe_current_period_end',

        'status',
    ];

    protected $casts = [
        'starts_at' => 'date',
        'ends_at' => 'date',
        'next_renewal_at' => 'date',
        'grace_until' => 'date',
        'paid_at' => 'date',
        'stripe_current_period_end' => 'datetime',
    ];

    public function coach()
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    public function plan()
    {
        return $this->belongsTo(MembershipPlan::class, 'membership_plan_id');
    }
}
