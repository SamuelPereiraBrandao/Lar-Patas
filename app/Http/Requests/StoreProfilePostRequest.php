<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProfilePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            'body' => 'nullable|string|max:2000',
            'pet_id' => 'nullable|integer|exists:pets,id',
            'images' => 'nullable|array|max:5',
            'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:3072',
        ];
    }
}
