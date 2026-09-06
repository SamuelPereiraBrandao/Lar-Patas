<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PetFavorite extends Model
{
    protected $fillable = ['user_id', 'pet_id'];
}
