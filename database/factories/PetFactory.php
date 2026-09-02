<?php

namespace Database\Factories;

use App\Models\Pet;
use Illuminate\Database\Eloquent\Factories\Factory;

class PetFactory extends Factory
{
    protected $model = Pet::class;

    public function definition(): array
    {
        return ['name' => fake()->firstName(), 'species' => 'dog', 'breed' => 'SRD', 'birth_date' => fake()->dateTimeBetween('-8 years', '-4 months')->format('Y-m-d'), 'size' => 'medium', 'sex' => 'female', 'city' => 'São Paulo, SP', 'temperament' => 'Dócil e sociável', 'description' => fake()->paragraph(), 'status' => 'available'];
    }
}
