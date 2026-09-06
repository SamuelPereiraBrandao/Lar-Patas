<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfilePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('post')->user_id === $this->user()?->id;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            'body' => 'required|string|max:2000',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'body.required' => 'Escreva o texto da publicação.',
            'body.max' => 'O texto deve ter no máximo 2.000 caracteres.',
        ];
    }
}
