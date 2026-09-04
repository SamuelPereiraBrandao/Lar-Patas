<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Pet extends Model
{
    use HasFactory;

    protected $fillable = ['owner_id', 'shelter_id', 'name', 'species', 'breed', 'birth_date', 'size', 'sex', 'city', 'temperament', 'description', 'status', 'queue_position', 'triage_notes', 'image_path', 'gallery_paths'];

    protected $casts = ['birth_date' => 'date', 'gallery_paths' => 'array'];

    protected $appends = ['image_url', 'gallery_urls', 'age_label'];

    public function adoptions(): HasMany
    {
        return $this->hasMany(Adoption::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function shelter(): BelongsTo
    {
        return $this->belongsTo(Shelter::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(PetLike::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? Storage::url($this->image_path) : null;
    }

    public function getGalleryUrlsAttribute(): array
    {
        return collect($this->gallery_paths ?? [])->map(fn ($path) => Storage::url($path))->values()->all();
    }

    public function getAgeLabelAttribute(): string
    {
        return $this->birth_date ? ((int) $this->birth_date->diffInYears(now()).' ano(s)') : 'Idade não informada';
    }
}
