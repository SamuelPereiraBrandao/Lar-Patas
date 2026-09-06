<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PetHealthRecord extends Model
{
    protected $fillable = ['pet_id', 'user_id', 'kind', 'title', 'performed_at', 'due_at', 'notes'];
}
