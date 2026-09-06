<?php

namespace Tests\Feature;

use App\Models\Pet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProfileValidationTest extends TestCase
{
    use RefreshDatabase;

    public static function imageEndpoints(): array
    {
        return [
            'avatar' => ['/api/profile/avatar', 'avatar'],
            'banner' => ['/api/profile/banner', 'banner'],
            'post' => ['/api/profile/posts', 'images'],
        ];
    }

    #[DataProvider('imageEndpoints')]
    public function test_images_above_three_megabytes_are_rejected(string $endpoint, string $field): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $image = UploadedFile::fake()->createWithContent('photo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='))->size(3073);

        $this->actingAs($user, 'sanctum')->postJson($endpoint, [$field => $field === 'images' ? [$image] : $image])
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field === 'images' ? 'images.0' : $field);

        $this->assertDatabaseCount('profile_posts', 0);
        $this->assertNull($user->fresh()->avatar_path);
        $this->assertNull($user->fresh()->banner_path);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    #[DataProvider('imageEndpoints')]
    public function test_images_at_three_megabytes_are_accepted(string $endpoint, string $field): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $image = UploadedFile::fake()->createWithContent('photo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='))->size(3072);

        $response = $this->actingAs($user, 'sanctum')->postJson($endpoint, [$field => $field === 'images' ? [$image] : $image]);
        if ($field === 'images') {
            $response->assertCreated();
        } else {
            $response->assertOk();
        }
        $this->assertDatabaseCount('profile_posts', 1);
        Storage::disk('public')->assertExists($response->json('post.image_path'));
    }

    public function test_post_edit_authorization_precedes_validation(): void
    {
        $author = User::factory()->create();
        $post = $author->profilePosts()->create(['body' => 'Texto original']);

        $this->actingAs(User::factory()->create(), 'sanctum')->patchJson('/api/profile/posts/'.$post->id, ['body' => ''])
            ->assertForbidden();

        $this->assertSame('Texto original', $post->fresh()->body);
    }

    public function test_family_pet_edit_authorization_precedes_location_validation(): void
    {
        $pet = Pet::factory()->create(['owner_id' => User::factory()->create()->id, 'ownership_kind' => 'guardian']);

        $this->actingAs(User::factory()->create(['city' => null, 'state' => null]), 'sanctum')
            ->putJson('/api/profile/pets/'.$pet->id, ['lives_with_owner' => true])
            ->assertForbidden();

        $this->assertSame($pet->name, $pet->fresh()->name);
    }

    public function test_empty_post_keeps_its_validation_message(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')->postJson('/api/profile/posts', [])
            ->assertUnprocessable()
            ->assertExactJson(['message' => 'Escreva algo ou selecione uma foto para publicar.']);

        $this->assertDatabaseCount('profile_posts', 0);
    }
}
