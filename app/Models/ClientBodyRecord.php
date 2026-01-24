<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientBodyRecord extends Model
{
    protected $table = 'client_body_records';

    protected $fillable = [
        'client_id',
        'weight_kg',
        'recorded_at',
        'source',
        'notes',
    ];

    protected $casts = [
        'weight_kg'   => 'decimal:2',
        'recorded_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeLatestFirst($query)
    {
        return $query->orderByDesc('recorded_at')->orderByDesc('id');
    }
}
