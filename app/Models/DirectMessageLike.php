<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DirectMessageLike extends Model
{
    protected $fillable = ['direct_message_id', 'user_id'];
}
