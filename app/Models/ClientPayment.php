<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientPayment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'coach_id',
        'client_id',
        'client_membership_id',
        'amount',
        'discount',
        'final_amount',
        'payment_method',
        'payment_date',
        'notes',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'discount' => 'decimal:2',
        'final_amount' => 'decimal:2',
        'payment_date' => 'date',
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

    public function membership()
    {
        return $this->belongsTo(ClientMembership::class);
    }
}