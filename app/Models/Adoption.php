<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Adoption extends Model
{
    protected $fillable = ['user_id', 'pet_id', 'applicant_name', 'email', 'phone', 'housing_type', 'has_other_pets', 'message', 'status', 'pickup_at', 'pickup_timezone', 'pickup_location', 'pickup_message', 'pickup_code', 'released_at', 'scheduled_by', 'released_by'];

    protected $casts = ['has_other_pets' => 'boolean', 'pickup_at' => 'datetime', 'released_at' => 'datetime', 'pickup_code' => 'encrypted'];

    protected $hidden = ['pickup_code'];

    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
