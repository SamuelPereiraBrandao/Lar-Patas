<?php

namespace Tests\Feature;

use App\Models\Pet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PetListingTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_only_matching_pets(): void
    {
        Pet::factory()->create(['name' => 'Luna', 'species' => 'dog', 'size' => 'medium']);
        Pet::factory()->create(['name' => 'Mingau', 'species' => 'cat', 'size' => 'small']);
        $this->getJson('/api/pets?species=Cachorro')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Luna');
    }
}
