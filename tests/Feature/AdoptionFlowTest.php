<?php

namespace Tests\Feature;

use App\Models\Pet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdoptionFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_valid_request_registers_the_user_interest(): void
    {
        $pet = Pet::factory()->create(['status' => 'available']);
        $this->actingAs(User::factory()->create(), 'sanctum')->postJson("/api/pets/{$pet->id}/adoptions", ['applicant_name' => 'Ana Silva', 'email' => 'ana@example.test', 'phone' => '11999999999', 'housing_type' => 'Apartamento', 'has_other_pets' => false, 'message' => 'Tenho estrutura e quero cuidar com carinho.'])->assertCreated();
        $this->assertDatabaseHas('adoptions', ['pet_id' => $pet->id, 'status' => 'pending']);
        $this->assertDatabaseHas('pets', ['id' => $pet->id, 'status' => 'available']);
    }
}
