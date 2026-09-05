<?php

namespace Tests\Feature;

use App\Models\Pet;
use App\Models\Shelter;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FamilyPetLocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrative_pet_uses_shelter_location_and_can_move_elsewhere(): void
    {
        $state = State::create(['name' => 'Santa Catarina', 'code' => 'SC']);
        $state->cities()->create(['name' => 'Blumenau']);
        $shelter = Shelter::create(['name' => 'Sede', 'city' => 'Joinville', 'state' => 'SC']);
        $pet = Pet::factory()->create();
        $details = $pet->only(['name', 'species', 'size', 'sex', 'temperament', 'description', 'status']);

        $this->actingAs(User::factory()->create(), 'sanctum')->putJson('/api/pets/'.$pet->id, [...$details, 'shelter_id' => $shelter->id, 'city' => 'Blumenau', 'state' => 'SC'])
            ->assertOk()->assertJsonPath('data.city', 'Joinville')->assertJsonPath('data.state', 'SC');
        $this->assertDatabaseHas('pets', ['id' => $pet->id, 'shelter_id' => $shelter->id, 'city' => 'Joinville', 'state' => 'SC']);

        $this->putJson('/api/pets/'.$pet->id, [...$details, 'shelter_id' => null, 'city' => 'Blumenau', 'state' => 'SC'])
            ->assertOk()->assertJsonPath('data.city', 'Blumenau')->assertJsonPath('data.state', 'SC');
        $this->assertDatabaseHas('pets', ['id' => $pet->id, 'shelter_id' => null, 'city' => 'Blumenau', 'state' => 'SC']);
    }

    public function test_administrative_pet_rejects_city_outside_selected_state(): void
    {
        $state = State::create(['name' => 'Santa Catarina', 'code' => 'SC']);
        $state->cities()->create(['name' => 'Blumenau']);
        $pet = Pet::factory()->create(['city' => 'Blumenau', 'state' => 'SC']);
        $details = $pet->only(['name', 'species', 'size', 'sex', 'temperament', 'description', 'status']);

        $this->actingAs(User::factory()->create(), 'sanctum')->putJson('/api/pets/'.$pet->id, [...$details, 'city' => 'Curitiba', 'state' => 'SC'])
            ->assertUnprocessable()->assertJsonPath('errors.city.0', 'Escolha uma cidade da UF selecionada.');
        $this->assertDatabaseHas('pets', ['id' => $pet->id, 'city' => 'Blumenau', 'state' => 'SC']);
    }

    public function test_administrative_pet_can_use_shelter_without_manual_location(): void
    {
        $shelter = Shelter::create(['name' => 'Sede', 'city' => 'Joinville', 'state' => 'SC']);
        $pet = Pet::factory()->create();
        $details = $pet->only(['name', 'species', 'size', 'sex', 'temperament', 'description', 'status']);

        $this->actingAs(User::factory()->create(), 'sanctum')->putJson('/api/pets/'.$pet->id, [...$details, 'shelter_id' => $shelter->id])
            ->assertOk()->assertJsonPath('data.city', 'Joinville')->assertJsonPath('data.state', 'SC');
        $this->assertDatabaseHas('pets', ['id' => $pet->id, 'shelter_id' => $shelter->id, 'city' => 'Joinville', 'state' => 'SC']);
    }

    public function test_family_pet_uses_owner_location_and_can_move_elsewhere(): void
    {
        Storage::fake('public');
        $state = State::create(['name' => 'Santa Catarina', 'code' => 'SC']);
        $state->cities()->create(['name' => 'Blumenau']);
        $state->cities()->create(['name' => 'Joinville']);
        $user = User::factory()->create(['city' => 'Blumenau', 'state' => 'SC']);
        $details = Pet::factory()->make()->only(['name', 'species', 'size', 'sex', 'temperament', 'description']);
        $response = $this->actingAs($user, 'sanctum')->postJson('/api/profile/pets', [...$details, 'lives_with_owner' => true, 'photos' => [UploadedFile::fake()->createWithContent('cover.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='))], 'city' => 'Ignored', 'state' => 'XX'])->assertCreated()->assertJsonPath('data.lives_with_owner', true);
        $id = $response->json('data.id');
        Storage::disk('public')->assertExists($response->json('data.image_path'));
        $this->assertDatabaseHas('pets', ['id' => $id, 'city' => 'Blumenau', 'state' => 'SC', 'lives_with_owner' => true]);
        $this->putJson('/api/profile/pets/'.$id, [...$details, 'lives_with_owner' => false, 'city' => 'Joinville', 'state' => 'SC'])->assertOk()->assertJsonPath('data.lives_with_owner', false);
        $this->assertDatabaseHas('pets', ['id' => $id, 'city' => 'Joinville', 'state' => 'SC', 'lives_with_owner' => false]);
    }

    public function test_missing_profile_location_returns_422(): void
    {
        $user = User::factory()->create(['city' => null, 'state' => null]);
        $this->actingAs($user, 'sanctum')->postJson('/api/profile/pets', ['lives_with_owner' => true])->assertUnprocessable()->assertJsonValidationErrors(['lives_with_owner']);
        $this->assertDatabaseCount('pets', 0);
    }

    public function test_city_outside_selected_state_returns_422(): void
    {
        $state = State::create(['name' => 'Santa Catarina', 'code' => 'SC']);
        $state->cities()->create(['name' => 'Blumenau']);
        $this->actingAs(User::factory()->create(), 'sanctum')->postJson('/api/profile/pets', ['lives_with_owner' => false, 'state' => 'SC', 'city' => 'Curitiba'])->assertUnprocessable()->assertJsonValidationErrors(['city']);
        $this->assertDatabaseCount('pets', 0);
    }

    public function test_other_location_requires_state(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')->postJson('/api/profile/pets', ['lives_with_owner' => false, 'city' => 'Blumenau'])->assertUnprocessable()->assertJsonValidationErrors(['state']);
        $this->assertDatabaseCount('pets', 0);
    }
}
