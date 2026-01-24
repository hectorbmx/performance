<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MembershipPlan extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'membership_plans';

    protected $fillable = [
        'name',
        'description',
        'billing_cycle_days',
        'is_active',
        'client_limit',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
