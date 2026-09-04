<?php

namespace Database\Seeders;

use App\Models\Pet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class PetImageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $photos = [
            'Luna' => [
                'https://images.unsplash.com/photo-1552053831-71594a27632d?auto=format&fit=crop&w=1400&q=90',
                'https://images.unsplash.com/photo-1587300003388-59208cc962cb?auto=format&fit=crop&w=1400&q=90',
                'https://images.unsplash.com/photo-1543466835-00a7907e9de1?auto=format&fit=crop&w=1400&q=90',
            ],
            'Mingau' => [
                'https://images.unsplash.com/photo-1514888286974-6c03e2ca1dba?auto=format&fit=crop&w=1400&q=90',
                'https://images.unsplash.com/photo-1495360010541-f48722b34f7d?auto=format&fit=crop&w=1400&q=90',
                'https://images.unsplash.com/photo-1573865526739-10659fec78a5?auto=format&fit=crop&w=1400&q=90',
            ],
            'Thor' => [
                'https://images.unsplash.com/photo-1552053831-71594a27632d?auto=format&fit=crop&w=1400&q=90',
                'https://images.unsplash.com/photo-1587300003388-59208cc962cb?auto=format&fit=crop&w=1400&q=90',
                'https://images.unsplash.com/photo-1543466835-00a7907e9de1?auto=format&fit=crop&w=1400&q=90',
            ],
        ];

        foreach ($photos as $name => $urls) {
            $pet = Pet::query()->where('name', $name)->first();
            if (! $pet || $pet->image_path) {
                continue;
            }

            $paths = [];
            foreach ($urls as $index => $url) {
                $path = "pets/seed/{$pet->id}-".($index + 1).'.jpg';
                if (! Storage::disk('public')->exists($path)) {
                    $response = Http::withoutVerifying()->timeout(30)->withUserAgent('Lar-e-Patas-Seed/1.0')->get($url);
                    if (! $response->successful()) {
                        continue;
                    }
                    Storage::disk('public')->put($path, $response->body());
                }
                $paths[] = $path;
            }

            if ($paths) {
                $pet->update(['image_path' => array_shift($paths), 'gallery_paths' => $paths]);
            }
        }
    }
}
