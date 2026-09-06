<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadAvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            'avatar' => 'required|image|mimes:jpg,jpeg,png,webp|max:3072',
        ];
    }
}
