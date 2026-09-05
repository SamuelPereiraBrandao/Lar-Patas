<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class ProfilePost extends Model
{
    protected $fillable = ['user_id', 'pet_id', 'body', 'image_path', 'gallery_paths'];

    protected $casts = ['gallery_paths' => 'array'];

    protected $appends = ['image_url', 'gallery_urls'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ProfileComment::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(ProfilePostLike::class);
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? Storage::url($this->image_path) : null;
    }

    public function getGalleryUrlsAttribute(): array
    {
        return collect([$this->image_path, ...($this->gallery_paths ?? [])])
            ->filter()
            ->map(fn ($path) => Storage::url($path))
            ->values()
            ->all();
    }
}
