<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserBlock extends Model
{
    protected $fillable = ['user_id', 'blocked_user_id'];

    public static function between(int $first, int $second): bool
    {
        return static::where(fn ($q) => $q->where('user_id', $first)->where('blocked_user_id', $second))->orWhere(fn ($q) => $q->where('user_id', $second)->where('blocked_user_id', $first))->exists();
    }

    public static function excludedIds(?int $viewer): array
    {
        if (! $viewer) {
            return [];
        }

        return static::where('user_id', $viewer)->pluck('blocked_user_id')->merge(static::where('blocked_user_id', $viewer)->pluck('user_id'))->unique()->all();
    }
}
