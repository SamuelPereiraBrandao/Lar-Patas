<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shelter extends Model
{
    protected $fillable = ['name', 'district', 'city', 'state', 'address', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function pets(): HasMany
    {
        return $this->hasMany(Pet::class);
    }
}
