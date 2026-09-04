<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfileComment extends Model
{
    protected $fillable = ['profile_post_id', 'user_id', 'body'];

    public function post(): BelongsTo
    {
        return $this->belongsTo(ProfilePost::class, 'profile_post_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
