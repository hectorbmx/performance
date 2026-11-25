<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class WorkoutAthleteSetting extends Model {
    protected $guarded = ['id'];
    protected $casts = ['client_id' => 'integer', 'coach_id' => 'integer', 'generator_enabled' => 'boolean',
        'blocked_exercise_codes' => 'array', 'blocked_demands' => 'array'];
}
