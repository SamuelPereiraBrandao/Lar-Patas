<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAdoptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['applicant_name' => 'required|string|max:120', 'email' => 'required|email|max:120', 'phone' => 'required|string|max:30', 'housing_type' => 'required|in:Casa com quintal,Casa sem quintal,Apartamento', 'has_other_pets' => 'boolean', 'message' => 'required|string|max:2000'];
    }
}
