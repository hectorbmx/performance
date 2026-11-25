<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AthleteProfile extends Model
{
    protected $fillable = ['user_app_id','first_name','last_name','privacy_accepted_at','privacy_version'];
    protected $casts = ['privacy_accepted_at'=>'datetime'];
}
