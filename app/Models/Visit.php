<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Visit extends Model
{
    protected $fillable = ['pet_id', 'adoption_id', 'scheduled_at', 'status', 'notes'];

    protected $casts = ['scheduled_at' => 'datetime'];
}
