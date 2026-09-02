<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreShelterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:120',
            'district' => 'nullable|string|max:100',
            'city' => 'required|string|max:100',
            'state' => 'required|string|size:2',
            'address' => 'nullable|string|max:180',
            'active' => 'boolean',
        ];
    }
}
