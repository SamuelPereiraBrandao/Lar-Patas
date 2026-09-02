<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Adoption extends Model
{
    protected $fillable = ['user_id', 'pet_id', 'applicant_name', 'email', 'phone', 'housing_type', 'has_other_pets', 'message', 'status'];

    protected $casts = ['has_other_pets' => 'boolean'];

    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
