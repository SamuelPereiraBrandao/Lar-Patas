<?php

namespace Tests\Feature;

use App\Models\Pet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PetPhotoEditingTest extends TestCase
{
    use RefreshDatabase;

    public static function endpoints(): array
    {
        return ['profile' => ['/api/profile/pets'], 'administration' => ['/api/pets']];
    }

    #[DataProvider('endpoints')]
    public function test_creation_without_photo_returns_422(string $endpoint): void
    {
        $pet = Pet::factory()->make();
        $this->actingAs(User::factory()->create(), 'sanctum')->postJson($endpoint, $this->details($pet))
            ->assertUnprocessable()
            ->assertJsonPath('errors.photos.0', 'Adicione pelo menos uma foto para criar o pet.');
        $this->assertDatabaseCount('pets', 0);
    }

    public function test_administration_can_create_pet_with_single_image(): void
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->createWithContent('cover.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
        $response = $this->actingAs(User::factory()->create(), 'sanctum')->postJson('/api/pets', [...$this->details(Pet::factory()->make()), 'image' => $file])->assertCreated();
        $this->assertDatabaseHas('pets', ['id' => $response->json('data.id'), 'image_path' => 'pets/'.$file->hashName()]);
        Storage::disk('public')->assertExists('pets/'.$file->hashName());
    }

    #[DataProvider('endpoints')]
    public function test_existing_cover_and_removal_are_saved(string $endpoint): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $pet = Pet::factory()->create(['owner_id' => $user->id, 'ownership_kind' => 'guardian', 'image_path' => 'pets/old.jpg', 'gallery_paths' => ['pets/second.jpg', 'pets/third.jpg']]);
        Storage::disk('public')->put('pets/old.jpg', 'old');
        Storage::disk('public')->put('pets/second.jpg', 'second');
        Storage::disk('public')->put('pets/third.jpg', 'third');

        $this->actingAs($user, 'sanctum')->putJson($endpoint.'/'.$pet->id, [...$this->details($pet), 'removed_photo_paths' => ['pets/old.jpg'], 'cover_photo_path' => 'pets/third.jpg'])->assertOk()->assertJsonPath('data.image_path', 'pets/third.jpg');

        $this->assertSame('pets/third.jpg', $pet->fresh()->image_path);
        $this->assertSame(['pets/second.jpg'], $pet->fresh()->gallery_paths);
        Storage::disk('public')->assertMissing('pets/old.jpg');
        Storage::disk('public')->assertExists(['pets/second.jpg', 'pets/third.jpg']);
    }

    #[DataProvider('endpoints')]
    public function test_new_upload_can_be_the_cover(string $endpoint): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $pet = Pet::factory()->create(['owner_id' => $user->id, 'ownership_kind' => 'guardian', 'image_path' => 'pets/old.jpg']);
        $file = UploadedFile::fake()->createWithContent('cover.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));

        $response = $this->actingAs($user, 'sanctum')->postJson($endpoint.'/'.$pet->id, [...$this->details($pet), '_method' => 'PUT', 'photos' => [$file], 'cover_photo_index' => 0])->assertOk();

        $this->assertSame('pets/gallery/'.$file->hashName(), $pet->fresh()->image_path);
        $this->assertSame(['pets/old.jpg'], $pet->fresh()->gallery_paths);
        $response->assertJsonPath('data.image_path', $pet->fresh()->image_path);
        Storage::disk('public')->assertExists($pet->fresh()->image_path);
    }

    public static function invalidChanges(): array
    {
        return [
            'foreign cover' => [['cover_photo_path' => 'pets/foreign.jpg'], 'cover_photo_path'],
            'foreign removal' => [['removed_photo_paths' => ['pets/foreign.jpg']], 'removed_photo_paths'],
            'last photo' => [['removed_photo_paths' => ['pets/old.jpg']], 'removed_photo_paths'],
            'missing upload' => [['cover_photo_index' => 0], 'cover_photo_index'],
        ];
    }

    #[DataProvider('invalidChanges')]
    public function test_invalid_changes_return_422_without_changing_photos(array $changes, string $field): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $pet = Pet::factory()->create(['owner_id' => $user->id, 'ownership_kind' => 'guardian', 'image_path' => 'pets/old.jpg']);
        Storage::disk('public')->put('pets/old.jpg', 'old');
        Storage::disk('public')->put('pets/foreign.jpg', 'foreign');

        $this->actingAs($user, 'sanctum')->putJson('/api/profile/pets/'.$pet->id, [...$this->details($pet), ...$changes])->assertUnprocessable()->assertJsonValidationErrors([$field === 'removed_photo_paths.0' ? 'removed_photo_paths' : $field]);

        $this->assertSame('pets/old.jpg', $pet->fresh()->image_path);
        Storage::disk('public')->assertExists(['pets/old.jpg', 'pets/foreign.jpg']);
    }

    public function test_another_user_cannot_edit_owned_photos(): void
    {
        $pet = Pet::factory()->create(['owner_id' => User::factory()->create()->id, 'ownership_kind' => 'guardian', 'image_path' => 'pets/old.jpg']);
        $this->actingAs(User::factory()->create(), 'sanctum')->putJson('/api/profile/pets/'.$pet->id, [...$this->details($pet), 'removed_photo_paths' => ['pets/old.jpg']])->assertForbidden();
        $this->assertSame('pets/old.jpg', $pet->fresh()->image_path);
    }

    public function test_guest_cannot_edit_owned_photos(): void
    {
        $pet = Pet::factory()->create();
        $this->putJson('/api/profile/pets/'.$pet->id, $this->details($pet))->assertUnauthorized();
    }

    public function test_gallery_limit_returns_422_without_storing_uploads(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $pet = Pet::factory()->create(['owner_id' => $user->id, 'ownership_kind' => 'guardian', 'image_path' => 'pets/cover.jpg', 'gallery_paths' => array_map(fn (int $index): string => "pets/{$index}.jpg", range(1, 9))]);
        $file = UploadedFile::fake()->createWithContent('extra.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));

        $this->actingAs($user, 'sanctum')->postJson('/api/profile/pets/'.$pet->id, [...$this->details($pet), '_method' => 'PUT', 'photos' => [$file]])->assertUnprocessable()->assertJsonValidationErrors(['photos']);

        $this->assertSame('pets/cover.jpg', $pet->fresh()->image_path);
        $this->assertCount(9, $pet->fresh()->gallery_paths);
        Storage::disk('public')->assertMissing('pets/gallery/'.$file->hashName());
    }

    public function test_new_pet_saves_its_first_photo_as_cover(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $pet = Pet::factory()->make();
        $file = UploadedFile::fake()->createWithContent('cover.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/profile/pets', [...$this->details($pet), 'photos' => [$file], 'cover_photo_index' => 0])->assertCreated();

        $this->assertDatabaseHas('pets', ['id' => $response->json('data.id'), 'owner_id' => $user->id, 'ownership_kind' => 'guardian', 'image_path' => 'pets/gallery/'.$file->hashName()]);
        Storage::disk('public')->assertExists('pets/gallery/'.$file->hashName());
    }

    /** @return array<string, mixed> */
    private function details(Pet $pet): array
    {
        return $pet->only(['name', 'species', 'breed', 'size', 'sex', 'city', 'temperament', 'description', 'status']);
    }
}
