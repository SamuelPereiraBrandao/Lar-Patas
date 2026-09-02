<?php

namespace Database\Seeders;

use App\Models\Pet;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::query()->firstOrCreate(['email' => 'admin@larepatas.test'], ['name' => 'Admin Lar & Patas', 'password' => Hash::make('password')]);
        foreach ([
            ['name' => 'Luna', 'species' => 'dog', 'breed' => 'Vira-lata', 'birth_date' => '2023-04-10', 'size' => 'medium', 'sex' => 'female', 'city' => 'São Paulo, SP', 'temperament' => 'Carinhosa e brincalhona', 'description' => 'Luna ama passeios tranquilos, brinquedos de corda e companhia. Está vacinada e pronta para conhecer sua família.', 'status' => 'available', 'image_path' => null],
            ['name' => 'Mingau', 'species' => 'cat', 'breed' => 'SRD', 'birth_date' => '2024-01-21', 'size' => 'small', 'sex' => 'male', 'city' => 'Campinas, SP', 'temperament' => 'Curioso e afetuoso', 'description' => 'Mingau é um gatinho sociável que procura uma casa segura, com telas nas janelas e muito carinho.', 'status' => 'available', 'image_path' => null],
            ['name' => 'Thor', 'species' => 'dog', 'breed' => 'Labrador', 'birth_date' => '2021-07-15', 'size' => 'large', 'sex' => 'male', 'city' => 'Santo André, SP', 'temperament' => 'Leal e tranquilo', 'description' => 'Thor é companheiro, adora água e se dá bem com crianças. Procura uma família com espaço e disposição para passeios.', 'status' => 'available', 'image_path' => null],
        ] as $pet) {
            Pet::query()->firstOrCreate(['name' => $pet['name']], $pet);
        }
    }
}
