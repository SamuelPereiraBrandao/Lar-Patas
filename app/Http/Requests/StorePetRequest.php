<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['name' => 'required|string|max:80', 'species' => 'required|in:dog,cat', 'breed' => 'nullable|string|max:100', 'birth_date' => 'nullable|date|before:today', 'size' => 'required|in:small,medium,large', 'sex' => 'required|in:male,female', 'city' => 'required|string|max:100', 'shelter_id' => 'nullable|exists:shelters,id', 'temperament' => 'required|string|max:150', 'description' => 'required|string|max:2000', 'status' => 'required|in:available,in_process,adopted', 'image' => 'nullable|image|max:5120', 'photos' => 'nullable|array|max:8', 'photos.*' => 'image|max:5120', 'removed_photo_paths' => 'nullable|array', 'removed_photo_paths.*' => 'string|max:255', 'cover_photo_path' => 'nullable|string|max:255'];
    }
}
