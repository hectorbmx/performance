<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use LogicException;
class WorkoutPrescriptionRevision extends Model {
    public $timestamps=false;protected $guarded=['id'];
    protected $casts=['content'=>'array','schema_version'=>'integer','created_at'=>'datetime'];
    protected static function booted():void {
        static::updating(fn()=>throw new LogicException('La revisión de prescripción es inmutable.'));
        static::deleting(fn()=>throw new LogicException('Se conserva el historial de prescripción.'));
    }
}
