<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class WorkoutTaxonomyTerm extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $casts = ['names' => 'array'];
}
