<?php

namespace Tests\Feature;

use App\Jobs\PublishAblyNotification;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FamilyPetOwnersTest extends TestCase
{
    use RefreshDatabase;

    public function test_creation_sends_invitation_and_pet_appears_only_after_acceptance(): void
    {
        Storage::fake('public');
        Queue::fake([PublishAblyNotification::class]);
        $creator = User::factory()->create();
        $owner = User::factory()->create();
        $details = Pet::factory()->make()->only(['name', 'species', 'size', 'sex', 'city', 'temperament', 'description']);
        $photo = UploadedFile::fake()->createWithContent('cover.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
        $response = $this->actingAs($creator, 'sanctum')->postJson('/api/profile/pets', [...$details, 'photos' => [$photo], 'owner_ids' => json_encode([$creator->id, $owner->id])])->assertCreated();
        $id = $response->json('data.id');
        $this->assertDatabaseHas('pet_caretakers', ['pet_id' => $id, 'user_id' => $owner->id, 'status' => 'pending']);
        $this->assertDatabaseMissing('pet_caretakers', ['pet_id' => $id, 'user_id' => $creator->id]);
        Storage::disk('public')->assertExists($response->json('data.image_path'));
        Queue::assertPushed(PublishAblyNotification::class);
        $this->getJson('/api/users/'.$owner->id.'/profile')->assertOk()->assertJsonPath('stats.pets', 0);
        $this->actingAs($owner, 'sanctum')->getJson('/api/notifications')->assertOk()->assertJsonPath('data.0.type', 'pet_caretaker_request')->assertJsonPath('data.0.pet_request_status', 'pending');
        $this->patchJson('/api/profile/pets/'.$id.'/owner-request', ['accept' => true])->assertOk();
        $this->assertDatabaseHas('pet_caretakers', ['pet_id' => $id, 'user_id' => $owner->id, 'status' => 'accepted']);
        $this->getJson('/api/users/'.$owner->id.'/profile')->assertOk()->assertJsonPath('stats.pets', 1)->assertJsonPath('adopted_pets.0.id', $id);
        $this->actingAs($owner, 'sanctum')->getJson('/api/profile/social')->assertOk()->assertJsonPath('stats.pets', 1)->assertJsonPath('my_pets.0.id', $id);
    }

    public function test_editing_owners_removes_pet_from_previous_profile(): void
    {
        $creator = User::factory()->create();
        $owner = User::factory()->create();
        $pet = Pet::factory()->create(['owner_id' => $creator->id, 'ownership_kind' => 'guardian']);
        $pet->caretakers()->attach($owner->id, ['status' => 'accepted']);
        $this->actingAs($creator, 'sanctum')->putJson('/api/profile/pets/'.$pet->id, [...$pet->only(['name', 'species', 'size', 'sex', 'city', 'temperament', 'description']), 'owner_ids' => '[]'])->assertOk()->assertJsonCount(0, 'data.caretakers');
        $this->assertDatabaseMissing('pet_caretakers', ['pet_id' => $pet->id, 'user_id' => $owner->id]);
        $this->getJson('/api/users/'.$owner->id.'/profile')->assertOk()->assertJsonPath('stats.pets', 0)->assertJsonCount(0, 'adopted_pets');
    }

    public function test_pending_associations_are_not_counted_and_duplicates_are_not_counted_twice(): void
    {
        $owner = User::factory()->create();
        $pet = Pet::factory()->create(['owner_id' => $owner->id]);
        $pet->caretakers()->attach($owner->id, ['status' => 'accepted']);
        $pending = Pet::factory()->create();
        $pending->caretakers()->attach($owner->id, ['status' => 'pending']);
        $this->actingAs($owner, 'sanctum')->getJson('/api/profile/social')->assertOk()->assertJsonPath('stats.pets', 1)->assertJsonCount(1, 'my_pets');
    }

    public function test_invalid_owner_returns_422_without_changing_pet(): void
    {
        $creator = User::factory()->create();
        $pet = Pet::factory()->create(['owner_id' => $creator->id, 'ownership_kind' => 'guardian']);
        $this->actingAs($creator, 'sanctum')->putJson('/api/profile/pets/'.$pet->id, [...$pet->only(['name', 'species', 'size', 'sex', 'city', 'temperament', 'description']), 'owner_ids' => [999999]])->assertUnprocessable()->assertJsonValidationErrors(['owner_ids.0']);
        $this->assertDatabaseCount('pet_caretakers', 0);
    }

    public function test_owner_search_returns_matching_profiles_without_email(): void
    {
        $viewer = User::factory()->create(['name' => 'Viewer']);
        $owner = User::factory()->create(['name' => 'Marina Silva']);
        $this->actingAs($viewer, 'sanctum')->getJson('/api/profile/pet-owners?search=Marina')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $owner->id)->assertJsonMissingPath('data.0.email');
    }

    public function test_accepted_owner_can_edit_but_cannot_delete_pet(): void
    {
        $creator = User::factory()->create();
        $owner = User::factory()->create();
        $pet = Pet::factory()->create(['owner_id' => $creator->id, 'ownership_kind' => 'guardian']);
        $pet->caretakers()->attach($owner->id, ['status' => 'accepted']);
        $this->actingAs($owner, 'sanctum')->putJson('/api/profile/pets/'.$pet->id, [...$pet->only(['name', 'species', 'size', 'sex', 'city', 'temperament', 'description']), 'name' => 'Mari atualizada'])->assertOk();
        $this->assertDatabaseHas('pets', ['id' => $pet->id, 'name' => 'Mari atualizada', 'owner_id' => $creator->id]);
        $this->deleteJson('/api/profile/pets/'.$pet->id)->assertForbidden();
        $this->deleteJson('/api/pets/'.$pet->id)->assertForbidden();
        $this->assertModelExists($pet);
    }

    public function test_creator_can_delete_pet_and_its_associations(): void
    {
        $creator = User::factory()->create();
        $owner = User::factory()->create();
        $pet = Pet::factory()->create(['owner_id' => $creator->id, 'ownership_kind' => 'guardian']);
        $pet->caretakers()->attach($owner->id, ['status' => 'accepted']);
        $this->actingAs($creator, 'sanctum')->deleteJson('/api/profile/pets/'.$pet->id)->assertNoContent();
        $this->assertModelMissing($pet);
        $this->assertDatabaseMissing('pet_caretakers', ['pet_id' => $pet->id]);
        $this->getJson('/api/users/'.$owner->id.'/profile')->assertOk()->assertJsonPath('stats.pets', 0);
    }

    public function test_pending_owner_cannot_edit_or_delete_and_can_decline(): void
    {
        $creator = User::factory()->create();
        $owner = User::factory()->create();
        $pet = Pet::factory()->create(['owner_id' => $creator->id, 'ownership_kind' => 'guardian']);
        $pet->caretakers()->attach($owner->id, ['status' => 'pending']);
        $details = $pet->only(['name', 'species', 'size', 'sex', 'city', 'temperament', 'description', 'status']);
        $this->actingAs($owner, 'sanctum')->putJson('/api/profile/pets/'.$pet->id, $details)->assertForbidden();
        $this->putJson('/api/pets/'.$pet->id, $details)->assertForbidden();
        $this->deleteJson('/api/profile/pets/'.$pet->id)->assertForbidden();
        $this->patchJson('/api/profile/pets/'.$pet->id.'/owner-request', ['accept' => false])->assertOk();
        $this->assertDatabaseMissing('pet_caretakers', ['pet_id' => $pet->id, 'user_id' => $owner->id]);
        $this->patchJson('/api/profile/pets/'.$pet->id.'/owner-request', ['accept' => true])->assertNotFound();
    }

    public function test_uninvited_user_cannot_accept_someone_elses_invitation(): void
    {
        $owner = User::factory()->create();
        $pet = Pet::factory()->create(['ownership_kind' => 'guardian']);
        $pet->caretakers()->attach($owner->id, ['status' => 'pending']);
        $this->actingAs(User::factory()->create(), 'sanctum')->patchJson('/api/profile/pets/'.$pet->id.'/owner-request', ['accept' => true])->assertNotFound();
        $this->assertDatabaseHas('pet_caretakers', ['pet_id' => $pet->id, 'user_id' => $owner->id, 'status' => 'pending']);
    }

    public function test_saving_again_preserves_accepted_owner_and_does_not_repeat_invitation(): void
    {
        Queue::fake([PublishAblyNotification::class]);
        $creator = User::factory()->create();
        $owner = User::factory()->create();
        $invited = User::factory()->create();
        $pet = Pet::factory()->create(['owner_id' => $creator->id, 'ownership_kind' => 'guardian']);
        $pet->caretakers()->attach($owner->id, ['status' => 'accepted']);
        $details = [...$pet->only(['name', 'species', 'size', 'sex', 'city', 'temperament', 'description']), 'owner_ids' => [$owner->id, $invited->id]];
        $this->actingAs($owner, 'sanctum')->putJson('/api/profile/pets/'.$pet->id, $details)->assertOk();
        $this->putJson('/api/profile/pets/'.$pet->id, $details)->assertOk();
        $this->assertDatabaseHas('pet_caretakers', ['pet_id' => $pet->id, 'user_id' => $owner->id, 'status' => 'accepted']);
        $this->assertDatabaseHas('pet_caretakers', ['pet_id' => $pet->id, 'user_id' => $invited->id, 'status' => 'pending']);
        $this->assertDatabaseCount('user_notifications', 1);
        Queue::assertPushed(PublishAblyNotification::class, 1);
    }
}
