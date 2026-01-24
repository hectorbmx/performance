<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientHealthProfile extends Model
{
    protected $table = 'client_health_profiles';

    protected $fillable = [
        'client_id',
        'state',
        'city',
        'zip_code',
        'birth_date',
        'gender',
        'height_cm',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'height_cm'  => 'integer',
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
}
