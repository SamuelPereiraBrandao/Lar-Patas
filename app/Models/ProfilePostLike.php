<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfilePostLike extends Model
{
    protected $fillable = ['profile_post_id', 'user_id'];
}
