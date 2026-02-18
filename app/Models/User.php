<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable,HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'stripe_customer_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

        // --- Relaciones SaaS ---

    public function coachProfile()
    {
        return $this->hasOne(\App\Models\CoachProfile::class);
    }

    public function clientProfile()
    {
        return $this->hasOne(\App\Models\ClientProfile::class);
    }

    // Clientes que pertenecen a este coach (tenant)
    public function coachedClients()
    {
        return $this->hasMany(\App\Models\ClientProfile::class, 'coach_id');
    }
    public function subscriptions()
        {
            return $this->hasMany(\App\Models\CoachSubscription::class, 'coach_id');
        }
    public function latestSubscription()
    {
        return $this->hasOne(\App\Models\CoachSubscription::class, 'coach_id')
            ->latestOfMany('ends_at');
    }
// Agregar estas relaciones al modelo User existente

    public function coachClientPlans()
    {
        return $this->hasMany(CoachClientPlan::class, 'coach_id');
    }

    public function clientMemberships()
    {
        return $this->hasMany(ClientMembership::class, 'coach_id');
    }
    public function devices()
    {
        return $this->hasMany(UserDevice::class);
    }

}
