<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    protected $fillable = [
        'coach_id', 'name', 'description', 'is_active'
    ];

    public function coach()
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    public function clients()
    {
        return $this->belongsToMany(Client::class, 'client_group')
            ->withTimestamps();
    }

    public function trainingAssignments()
    {
        return $this->hasMany(GroupTrainingAssignment::class);
    }
   

    
}
