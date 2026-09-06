<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentReport extends Model
{
    protected $fillable = ['user_id', 'profile_post_id', 'reason', 'status', 'resolution', 'reviewed_by'];
}
