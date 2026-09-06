<?php

namespace Tests\Feature;

use App\Models\Adoption;
use App\Models\DirectConversation;
use App\Models\FriendRequest;
use App\Models\Pet;
use App\Models\ProfilePost;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoCommunitySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_contains_consistent_usable_scenarios_without_external_messages(): void
    {
        Storage::fake('public');
        Notification::fake();
        Queue::fake();
        Http::preventStrayRequests();
        Http::fake(['https://images.unsplash.com/*' => Http::response('', 503)]);

        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('users', 30);
        $this->assertDatabaseCount('pets', 48);
        $this->assertDatabaseCount('shelters', 7);
        $this->assertDatabaseCount('adoptions', 108);
        $this->assertDatabaseCount('profile_posts', 126);
        $this->assertDatabaseCount('profile_comments', 378);
        $this->assertDatabaseCount('profile_post_likes', 630);
        $this->assertDatabaseCount('pet_caretakers', 24);
        $this->assertSame(20, Pet::where('status', 'available')->count());
        $this->assertSame(6, Adoption::whereNotNull('pickup_at')->whereNull('released_at')->count());
        $this->assertSame(10, Adoption::whereNotNull('released_at')->count());
        foreach (Adoption::whereNotNull('pickup_at')->get() as $adoption) {
            if ($adoption->released_at) {
                $this->assertSame($adoption->user_id, $adoption->pet->owner_id);
                $this->assertSame('adopted', $adoption->pet->status);
                $this->assertNull($adoption->pickup_code);
            } else {
                $this->assertSame('in_process', $adoption->pet->status);
                $this->assertNotSame($adoption->user_id, $adoption->pet->owner_id);
                $this->assertMatchesRegularExpression('/^[0-9]{6}$/', $adoption->pickup_code);
            }
        }
        foreach (ProfilePost::whereNotNull('pet_id')->get() as $post) {
            $this->assertTrue(Pet::ownedBy($post->user_id)->whereKey($post->pet_id)->exists());
            Storage::disk('public')->assertExists($post->image_path);
        }
        foreach (DirectConversation::all() as $conversation) {
            $this->assertTrue(FriendRequest::where('sender_id', $conversation->user_one_id)->where('recipient_id', $conversation->user_two_id)->where('status', 'accepted')->exists());
        }
        $admin = User::where('email', 'admin@larepatas.test')->sole();
        $this->actingAs($admin, 'sanctum')->getJson('/api/admin/pets')->assertOk()->assertJsonCount(48, 'data');
        $this->getJson('/api/profile/social')->assertOk()->assertJsonPath('profile.email', 'admin@larepatas.test');
        $this->getJson('/api/notifications')->assertOk();
        $this->assertSame(0, User::whereNotIn('housing_type', ['Casa com quintal', 'Casa sem quintal', 'Apartamento'])->count());
        $this->putJson('/api/profile', $admin->only(['name', 'phone', 'city', 'state', 'housing_type', 'has_other_pets', 'household_description']))->assertOk();
        $photo = UploadedFile::fake()->createWithContent('avatar.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
        $uploaded = $this->postJson('/api/profile/avatar', ['avatar' => $photo])->assertOk();
        Storage::disk('public')->assertExists($uploaded->json('user.avatar_path'));
        Notification::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_existing_demo_housing_is_corrected_without_losing_profile_data(): void
    {
        $user = User::factory()->create(['housing_type' => 'Casa com quintal cercado', 'avatar_path' => 'profiles/avatar.jpg']);
        $apartment = User::factory()->create(['housing_type' => 'Apartamento com telas']);
        $migration = require database_path('migrations/2026_09_06_022637_normalize_demo_housing_types.php');

        $migration->up();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'housing_type' => 'Casa com quintal', 'avatar_path' => 'profiles/avatar.jpg']);
        $this->assertDatabaseHas('users', ['id' => $apartment->id, 'housing_type' => 'Apartamento']);
        $this->actingAs($user, 'sanctum')->putJson('/api/profile', ['name' => $user->name, 'housing_type' => 'Casa com quintal cercado'])->assertUnprocessable()->assertJsonPath('errors.housing_type.0', 'Escolha um tipo de moradia válido na aba Informações.');
    }
}
