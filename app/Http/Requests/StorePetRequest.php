<?php

namespace App\Http\Requests;

use App\Models\City;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    /** @return array<\Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || $this->filled('shelter_id') || ! $this->filled('state')) {
                return;
            }

            if (! City::where('name', $this->input('city'))->whereHas('state', fn ($query) => $query->where('code', $this->input('state')))->exists()) {
                $validator->errors()->add('city', 'Escolha uma cidade da UF selecionada.');
            }
        }];
    }

    public function rules(): array
    {
        return ['name' => 'required|string|max:80', 'species' => 'required|in:dog,cat', 'breed' => 'nullable|string|max:100', 'birth_date' => 'nullable|date|before:today', 'size' => 'required|in:small,medium,large', 'sex' => 'required|in:male,female', 'city' => 'required_without:shelter_id|nullable|string|max:100', 'state' => 'nullable|string|size:2|exists:states,code', 'shelter_id' => 'nullable|exists:shelters,id', 'temperament' => 'required|string|max:150', 'description' => 'required|string|max:2000', 'status' => 'required|in:available,in_process,adopted', 'image' => 'nullable|image|max:3072', 'photos' => 'nullable|array|max:10', 'photos.*' => 'image|max:3072', 'removed_photo_paths' => 'nullable|array', 'removed_photo_paths.*' => 'string|max:255', 'cover_photo_path' => 'nullable|string|max:255', 'cover_photo_index' => 'nullable|integer|min:0|max:9'];
    }
}
