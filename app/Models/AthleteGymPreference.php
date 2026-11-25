<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AthleteGymPreference extends Model
{
    protected $guarded=['id'];
    protected $casts=['blocked_exercise_codes'=>'array','blocked_demands'=>'array'];
}
