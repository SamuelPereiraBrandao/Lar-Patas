<?php

namespace App\Http\Requests;

use App\Models\City;
use App\Models\Pet;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SaveFamilyPetRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }
        $pet = $this->route('pet');

        return ! $pet || (($pet->ownership_kind === 'guardian' || $pet->status === 'adopted')
            && Pet::ownedBy($user->id)->whereKey($pet->id)->exists());
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:80',
            'species' => 'required|in:dog,cat',
            'breed' => 'nullable|string|max:100',
            'birth_date' => 'nullable|date|before:today',
            'size' => 'required|in:small,medium,large',
            'sex' => 'required|in:male,female',
            'city' => 'required|string|max:100',
            'state' => 'required_if:lives_with_owner,false|nullable|string|size:2|exists:states,code',
            'lives_with_owner' => 'sometimes|boolean',
            'temperament' => 'required|string|max:150',
            'description' => 'required|string|max:2000',
            'photos' => 'nullable|array|max:10',
            'photos.*' => 'image|max:3072',
            'removed_photo_paths' => 'nullable|array|max:10',
            'removed_photo_paths.*' => 'string|max:255',
            'cover_photo_path' => 'nullable|string|max:255',
            'cover_photo_index' => 'nullable|integer|min:0|max:9',
            'owner_ids' => 'sometimes|array|max:20',
            'owner_ids.*' => 'required|integer|distinct|exists:users,id',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('owner_ids'))) {
            $this->merge(['owner_ids' => json_decode($this->input('owner_ids'), true)]);
        }
        if ($this->boolean('lives_with_owner') && $this->user()?->city && $this->user()?->state) {
            $this->merge(['city' => $this->user()->city, 'state' => $this->user()->state]);
        }
    }

    /** @return array<\Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('lives_with_owner')) {
                return;
            }
            if ($this->boolean('lives_with_owner') && (! $this->user()->city || ! $this->user()->state)) {
                $validator->errors()->add('lives_with_owner', 'Preencha a cidade e a UF do seu perfil ou escolha outra localização.');

                return;
            }
            if ($validator->errors()->hasAny(['city', 'state']) || ! $this->filled('state')) {
                return;
            }
            $cityExists = City::where('name', $this->input('city'))
                ->whereHas('state', fn ($query) => $query->where('code', $this->input('state')))
                ->exists();
            if (! $cityExists) {
                $validator->errors()->add('city', 'Escolha uma cidade da UF selecionada.');
            }
        }];
    }
}
