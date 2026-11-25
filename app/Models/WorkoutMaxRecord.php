<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use LogicException;
class WorkoutMaxRecord extends Model {
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $casts = ['value_original' => 'decimal:2', 'value_kg' => 'decimal:6', 'measured_at' => 'datetime', 'created_at' => 'datetime', 'evidence' => 'array'];
    protected static function booted(): void {
        static::updating(fn () => throw new LogicException('Corrige un RM agregando otro registro con procedencia.'));
        static::deleting(fn () => throw new LogicException('El historial de RM no se elimina.'));
    }
}
