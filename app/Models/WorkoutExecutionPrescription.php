<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class WorkoutExecutionPrescription extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'content' => 'array',
        'schema_version' => 'integer',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('La prescripción de una ejecución es inmutable.');
        });
        static::deleting(function () {
            throw new LogicException('No se elimina la prescripción del historial.');
        });
    }
}
