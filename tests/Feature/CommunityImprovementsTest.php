<?php

namespace Tests\Feature;

use App\Jobs\PublishAblyNotification;
use App\Models\Adoption;
use App\Models\ContentReport;
use App\Models\DirectConversation;
use App\Models\FriendRequest;
use App\Models\Pet;
use App\Models\Role;
use App\Models\User;
use App\Notifications\AdoptionCareReminder;
use App\Notifications\AdoptionPickupScheduled;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CommunityImprovementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_family_profile_and_adopted_detail_do_not_show_open_donation_interests(): void
    {
        $owner = User::factory()->create();
        $family = Pet::factory()->create(['owner_id' => $owner->id, 'status' => 'adopted', 'ownership_kind' => 'guardian']);
        $adopted = Pet::factory()->create(['owner_id' => $owner->id, 'status' => 'adopted', 'ownership_kind' => 'adoption']);
        $donation = Pet::factory()->create(['owner_id' => $owner->id, 'status' => 'available', 'ownership_kind' => 'adoption']);
        $this->adoption($owner, $adopted, ['status' => 'approved', 'released_at' => now()]);
        $this->adoption(User::factory()->create(), $adopted, ['status' => 'rejected']);
        $this->actingAs($owner, 'sanctum')->getJson('/api/profile/social')->assertOk()->assertJsonCount(2, 'adopted_pets')->assertJsonCount(3, 'my_pets')->assertJsonPath('stats.adoptions', 1);
        $profile = $this->getJson('/api/users/'.$owner->id.'/profile')->assertOk()->assertJsonCount(2, 'adopted_pets');
        $this->assertEqualsCanonicalizing([$family->id, $adopted->id], array_column($profile->json('adopted_pets'), 'id'));
        $this->getJson('/api/pets/'.$adopted->id)->assertOk()->assertJsonPath('data.is_interested', false)->assertJsonPath('data.can_adopt', false)->assertJsonMissingPath('data.adoptions_count')->assertJsonMissingPath('data.latest_interest_at');
        $this->getJson('/api/pets')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $donation->id);
        $this->assertDatabaseCount('adoptions', 2);
    }

    public function test_explore_filters_favorites_before_pagination_and_combines_other_filters(): void
    {
        $user = User::factory()->create();
        $dog = Pet::factory()->create(['species' => 'dog', 'created_at' => now()->subDays(3)]);
        $cat = Pet::factory()->create(['species' => 'cat', 'created_at' => now()->subDays(3)]);
        $dog->favorites()->create(['user_id' => $user->id]);
        $cat->favorites()->create(['user_id' => $user->id]);
        Pet::factory()->count(14)->create();
        $this->getJson('/api/pets?favorites=true')->assertUnauthorized();
        $this->actingAs($user, 'sanctum')->getJson('/api/pets?favorites=true')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('total', 2);
        $this->getJson('/api/pets?favorites=true&species=dog')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $dog->id)->assertJsonPath('data.0.is_favorited', true);
        $this->getJson('/api/pets?favorites=false')->assertOk()->assertJsonPath('total', 16);
        $this->postJson('/api/saved-searches', ['name' => 'Favorite dogs', 'filters' => ['favorites' => true, 'species' => 'dog']])->assertCreated()->assertJsonPath('data.filters.favorites', true);
        $this->actingAs(User::factory()->create(), 'sanctum')->getJson('/api/pets?favorites=true')->assertOk()->assertJsonCount(0, 'data');
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => 'admin'], ['label' => 'Admin']));

        return $user;
    }

    private function adoption(User $user, Pet $pet, array $attributes = []): Adoption
    {
        return Adoption::create([...['user_id' => $user->id, 'pet_id' => $pet->id, 'applicant_name' => $user->name, 'email' => $user->email, 'phone' => '123456789', 'housing_type' => 'Apartamento', 'has_other_pets' => false, 'message' => 'Quero adotar.', 'status' => 'pending'], ...$attributes]);
    }

    public function test_disabled_accounts_cannot_keep_using_authenticated_endpoints(): void
    {
        $user = User::factory()->create(['is_active' => false]);
        $user->createToken('old-device');
        $this->actingAs($user, 'sanctum')->getJson('/api/profile')->assertForbidden();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_disabled_account_cannot_finish_an_already_started_login(): void
    {
        $user = User::factory()->create(['is_active' => false, 'two_factor_code' => Hash::make('123456'), 'two_factor_expires_at' => now()->addMinutes(5)]);
        $this->withSession(['two_factor_user_id' => $user->id])->postJson('/two-factor/verify', ['code' => '123456'])->assertUnprocessable();
        $this->assertGuest();
    }

    public function test_private_adoption_and_admin_endpoints_reject_regular_accounts(): void
    {
        $user = User::factory()->create();
        $pet = Pet::factory()->create();
        $this->actingAs($user, 'sanctum')->getJson('/api/adoptions')->assertForbidden();
        $this->getJson('/api/dashboards/admin')->assertForbidden();
        $this->getJson('/api/admin/operations')->assertForbidden();
        $this->deleteJson('/api/pets/'.$pet->id)->assertForbidden();
        $this->assertModelExists($pet);
    }

    public function test_donor_dashboard_does_not_expose_pickup_details_or_applicant_contact(): void
    {
        $owner = User::factory()->create();
        $pet = Pet::factory()->create(['owner_id' => $owner->id]);
        $this->adoption(User::factory()->create(), $pet, ['pickup_code' => '123456', 'pickup_location' => 'Private address', 'pickup_at' => now()->addDay()]);
        $this->actingAs($owner, 'sanctum')->getJson('/api/dashboards/donor')->assertOk()->assertJsonMissingPath('data.0.adoptions.0.email')->assertJsonMissingPath('data.0.adoptions.0.pickup_location')->assertJsonMissingPath('data.0.adoptions.0.pickup_code');
    }

    public function test_login_attempts_are_limited_and_two_factor_secrets_are_hidden(): void
    {
        $user = User::factory()->create(['two_factor_code' => 'hidden-secret']);
        $this->assertArrayNotHasKey('two_factor_code', $user->toArray());
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/login', ['email' => $user->email, 'password' => 'wrong'])->assertUnprocessable();
        }
        $this->postJson('/login', ['email' => $user->email, 'password' => 'wrong'])->assertTooManyRequests();
    }

    public function test_blocking_removes_friendship_hides_feed_and_prevents_messages_and_comments(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        FriendRequest::create(['sender_id' => $first->id, 'recipient_id' => $second->id, 'status' => 'accepted']);
        $conversation = DirectConversation::create(['user_one_id' => $first->id, 'user_two_id' => $second->id]);
        $post = $second->profilePosts()->create(['body' => 'Public post']);
        $this->actingAs($first, 'sanctum')->putJson('/api/safety/blocks/'.$second->id)->assertOk();
        $this->assertDatabaseCount('friend_requests', 0);
        $this->getJson('/api/community/feed')->assertJsonCount(0, 'data');
        $this->postJson('/api/chat/conversations/'.$conversation->id.'/messages', ['body' => 'Hello'])->assertForbidden();
        $this->postJson('/api/profile/posts/'.$post->id.'/comments', ['body' => 'Hello'])->assertForbidden();
        $this->postJson('/api/users/'.$second->id.'/friend-requests')->assertForbidden();
        $this->actingAs($second, 'sanctum')->postJson('/api/chat/conversations/'.$conversation->id.'/messages', ['body' => 'Hello'])->assertForbidden();
        $this->assertDatabaseCount('direct_messages', 0);
        $this->actingAs($first, 'sanctum')->deleteJson('/api/safety/blocks/'.$second->id)->assertNoContent();
        $this->getJson('/api/community/feed')->assertJsonCount(1, 'data');
    }

    public function test_reports_require_a_reason_and_only_admin_can_hide_the_post(): void
    {
        $user = User::factory()->create();
        $post = User::factory()->create()->profilePosts()->create(['body' => 'Reported post']);
        $this->actingAs($user, 'sanctum')->postJson('/api/profile/posts/'.$post->id.'/reports', ['reason' => 'bad'])->assertUnprocessable();
        $this->postJson('/api/profile/posts/'.$post->id.'/reports', ['reason' => 'Contains inappropriate material.'])->assertCreated();
        $report = ContentReport::sole();
        $this->patchJson('/api/admin/reports/'.$report->id, ['status' => 'hidden', 'resolution' => 'Confirmed inappropriate content.'])->assertForbidden();
        $this->actingAs($this->admin(), 'sanctum')->patchJson('/api/admin/reports/'.$report->id, ['status' => 'hidden', 'resolution' => 'Confirmed inappropriate content.'])->assertOk();
        $this->getJson('/api/community/feed')->assertJsonCount(0, 'data');
        $this->getJson('/api/profile/posts/'.$post->id)->assertNotFound();
        $this->assertDatabaseHas('content_reports', ['id' => $report->id, 'status' => 'hidden']);
        $this->assertDatabaseHas('audit_logs', ['resource' => 'api/admin/reports/'.$report->id]);
    }

    public function test_favorites_are_idempotent_private_and_saved_searches_cannot_be_deleted_by_others(): void
    {
        $user = User::factory()->create();
        $pet = Pet::factory()->create();
        $this->actingAs($user, 'sanctum')->putJson('/api/pets/'.$pet->id.'/favorite')->assertOk();
        $this->putJson('/api/pets/'.$pet->id.'/favorite')->assertOk();
        $this->assertDatabaseCount('pet_favorites', 1);
        $this->getJson('/api/favorites')->assertJsonCount(1, 'data');
        $this->getJson('/api/pets/'.$pet->id)->assertJsonPath('data.is_favorited', true);
        $search = $this->postJson('/api/saved-searches', ['name' => 'Dogs', 'filters' => ['species' => 'dog']])->assertCreated()->json('data.id');
        $this->actingAs(User::factory()->create(), 'sanctum')->getJson('/api/favorites')->assertJsonCount(0, 'data')->assertJsonCount(0, 'searches');
        $this->deleteJson('/api/saved-searches/'.$search)->assertForbidden();
        $this->actingAs($user, 'sanctum')->deleteJson('/api/pets/'.$pet->id.'/favorite')->assertOk();
        $this->deleteJson('/api/saved-searches/'.$search)->assertNoContent();
        $this->assertDatabaseCount('pet_favorites', 0);
    }

    public function test_health_records_are_private_and_owned_by_the_correct_pet(): void
    {
        $owner = User::factory()->create();
        $pet = Pet::factory()->create(['owner_id' => $owner->id]);
        $other = Pet::factory()->create(['owner_id' => $owner->id]);
        $this->actingAs($owner, 'sanctum')->postJson('/api/pets/'.$pet->id.'/health', ['kind' => 'vaccine', 'title' => 'Annual vaccination', 'performed_at' => now()->toDateString()])->assertCreated();
        $this->getJson('/api/pets/'.$pet->id.'/health')->assertJsonCount(1, 'data')->assertJsonPath('can_edit', true);
        $this->deleteJson('/api/pets/'.$other->id.'/health/1')->assertNotFound();
        $this->actingAs(User::factory()->create(), 'sanctum')->getJson('/api/pets/'.$pet->id.'/health')->assertForbidden();
        $this->postJson('/api/pets/'.$pet->id.'/health', ['kind' => 'care', 'title' => 'Invalid author'])->assertForbidden();
        $this->assertDatabaseCount('pet_health_records', 1);
    }

    public function test_cancellation_invalidates_code_and_releases_reservation_without_transferring_ownership(): void
    {
        Queue::fake([PublishAblyNotification::class]);
        $user = User::factory()->create();
        $pet = Pet::factory()->create(['status' => 'in_process']);
        $adoption = $this->adoption($user, $pet, ['status' => 'approved', 'pickup_code' => '123456', 'pickup_at' => now()->addDay(), 'pickup_location' => 'Private address']);
        $this->actingAs(User::factory()->create(), 'sanctum')->patchJson('/api/adoptions/'.$adoption->id.'/care', ['action' => 'cancel', 'reason' => 'Cannot pick up the pet.'])->assertForbidden();
        $this->actingAs($user, 'sanctum')->patchJson('/api/adoptions/'.$adoption->id.'/care', ['action' => 'cancel', 'reason' => 'Cannot pick up the pet.'])->assertOk();
        $this->assertDatabaseHas('pets', ['id' => $pet->id, 'status' => 'available', 'owner_id' => null]);
        $this->assertNull($adoption->fresh()->pickup_code);
        $this->assertNull($adoption->fresh()->pickup_location);
        $this->actingAs($this->admin(), 'sanctum')->postJson('/api/adoptions/'.$adoption->id.'/release', ['code' => '123456'])->assertConflict();
        $this->assertDatabaseCount('profile_posts', 0);
        Queue::assertPushed(PublishAblyNotification::class);
    }

    public function test_rescheduling_requires_admin_confirmation_and_replaces_the_code(): void
    {
        Notification::fake();
        Queue::fake([PublishAblyNotification::class]);
        $user = User::factory()->create();
        $pet = Pet::factory()->create(['status' => 'in_process']);
        $oldDate = now()->addDay()->startOfMinute();
        $adoption = $this->adoption($user, $pet, ['status' => 'approved', 'pickup_code' => '123456', 'pickup_at' => $oldDate, 'pickup_timezone' => 'America/Sao_Paulo', 'pickup_location' => 'Shelter']);
        $newDate = now()->addDays(3)->startOfMinute()->toISOString();
        $this->actingAs($user, 'sanctum')->patchJson('/api/adoptions/'.$adoption->id.'/care', ['action' => 'reschedule', 'reason' => 'Need another available day.', 'requested_pickup_at' => $newDate])->assertOk();
        $this->assertTrue($oldDate->equalTo($adoption->fresh()->pickup_at));
        $payload = ['pickup_at' => $newDate, 'pickup_timezone' => 'America/Sao_Paulo', 'pickup_location' => 'Shelter', 'pickup_message' => 'We will be waiting.'];
        $this->patchJson('/api/adoptions/'.$adoption->id.'/pickup', $payload)->assertForbidden();
        $this->actingAs($this->admin(), 'sanctum')->patchJson('/api/adoptions/'.$adoption->id.'/pickup', $payload)->assertOk();
        $this->assertNull($adoption->fresh()->reschedule_requested_at);
        $this->assertNotSame('123456', $adoption->fresh()->pickup_code);
        $this->postJson('/api/adoptions/'.$adoption->id.'/release', ['code' => '123456'])->assertUnprocessable();
        Notification::assertSentTo($user, AdoptionPickupScheduled::class);
        Queue::assertPushed(PublishAblyNotification::class);
    }

    public function test_reminders_run_once_and_stale_pickup_emails_are_suppressed(): void
    {
        $this->freezeTime();
        Notification::fake();
        Queue::fake([PublishAblyNotification::class]);
        $user = User::factory()->create();
        $pet = Pet::factory()->create();
        $pickup = $this->adoption($user, $pet, ['status' => 'approved', 'pickup_at' => now()->addHours(4)]);
        $followup = $this->adoption($user, Pet::factory()->create(), ['status' => 'approved', 'released_at' => now()->subDays(8)]);
        $this->artisan('adoptions:send-reminders')->assertSuccessful();
        $this->artisan('adoptions:send-reminders')->assertSuccessful();
        Notification::assertSentToTimes($user, AdoptionCareReminder::class, 2);
        $this->assertDatabaseCount('user_notifications', 2);
        $mail = new AdoptionCareReminder($pickup, false, $pickup->pickup_at->toISOString());
        $this->assertTrue($mail->shouldSend($user, 'mail'));
        $pickup->update(['pickup_at' => now()->addDays(2)]);
        $this->assertFalse($mail->shouldSend($user, 'mail'));
        $this->assertNotNull($followup->fresh()->followup_sent_at);
        Queue::assertPushed(PublishAblyNotification::class, 2);
    }

    public function test_adopter_can_request_help_only_after_release(): void
    {
        Queue::fake([PublishAblyNotification::class]);
        $user = User::factory()->create();
        $adoption = $this->adoption($user, Pet::factory()->create());
        $payload = ['action' => 'followup', 'adaptation_status' => 'needs_help', 'adaptation_notes' => 'Need help with adaptation.'];
        $this->actingAs($user, 'sanctum')->patchJson('/api/adoptions/'.$adoption->id.'/care', $payload)->assertConflict();
        $adoption->update(['status' => 'approved', 'released_at' => now()]);
        $admin = $this->admin();
        $this->patchJson('/api/adoptions/'.$adoption->id.'/care', $payload)->assertOk();
        $this->assertDatabaseHas('adoptions', ['id' => $adoption->id, 'adaptation_status' => 'needs_help']);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $admin->id, 'type' => 'adoption_care']);
        $this->actingAs($admin, 'sanctum')->getJson('/api/admin/operations')->assertOk()->assertJsonPath('followups.0.id', $adoption->id);
        Queue::assertPushed(PublishAblyNotification::class);
    }

    public function test_upload_removes_metadata_and_preserves_a_viewable_image(): void
    {
        Storage::fake('public');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
        $metadata = "Location\0Private GPS";
        $chunk = pack('N', strlen($metadata)).'tEXt'.$metadata.pack('N', crc32('tEXt'.$metadata));
        $file = UploadedFile::fake()->createWithContent('photo.png', substr($png, 0, 33).$chunk.substr($png, 33));
        $user = User::factory()->create();
        $response = $this->actingAs($user, 'sanctum')->postJson('/api/profile/avatar', ['avatar' => $file])->assertOk();
        $path = $response->json('user.avatar_path');
        Storage::disk('public')->assertExists($path);
        $bytes = Storage::disk('public')->get($path);
        $this->assertStringNotContainsString('Private GPS', $bytes);
        $this->assertSame('image/png', getimagesizefromstring($bytes)['mime']);
    }

    public function test_support_response_reaches_adopter_and_resolves_the_pending_help_request(): void
    {
        Queue::fake([PublishAblyNotification::class]);
        $user = User::factory()->create();
        $adoption = $this->adoption($user, Pet::factory()->create(), ['status' => 'approved', 'released_at' => now(), 'followup_completed_at' => now(), 'adaptation_status' => 'needs_help']);
        $payload = ['action' => 'support', 'support_message' => 'A equipe vai acompanhar vocês amanhã.', 'resolved' => true];
        $this->actingAs($user, 'sanctum')->patchJson('/api/adoptions/'.$adoption->id.'/care', $payload)->assertForbidden();
        $this->actingAs($this->admin(), 'sanctum')->patchJson('/api/adoptions/'.$adoption->id.'/care', $payload)->assertOk();
        $this->assertNotNull($adoption->fresh()->followup_resolved_at);
        $this->getJson('/api/admin/operations')->assertJsonCount(0, 'followups');
        $this->actingAs($user, 'sanctum')->getJson('/api/dashboards/receiver')->assertJsonPath('data.0.support_message', $payload['support_message']);
        Queue::assertPushed(PublishAblyNotification::class);
    }

    public function test_adopted_pet_owner_can_edit_profile_without_reopening_adoption(): void
    {
        $user = User::factory()->create();
        $pet = Pet::factory()->create(['owner_id' => $user->id, 'ownership_kind' => 'adoption', 'status' => 'adopted']);
        $data = $pet->only(['name', 'species', 'size', 'sex', 'city', 'temperament', 'description']);
        $this->actingAs($user, 'sanctum')->putJson('/api/profile/pets/'.$pet->id, [...$data, 'name' => 'New name', 'status' => 'available', 'owner_id' => User::factory()->create()->id])->assertOk();
        $this->assertDatabaseHas('pets', ['id' => $pet->id, 'name' => 'New name', 'status' => 'adopted', 'owner_id' => $user->id]);
    }

    public function test_realtime_token_is_read_only_and_cannot_subscribe_to_other_accounts(): void
    {
        config(['services.ably.key' => 'test.key:'.base64_encode('test-secret')]);
        $user = User::factory()->create();
        $other = User::factory()->create();
        $own = DirectConversation::create(['user_one_id' => $user->id, 'user_two_id' => $other->id]);
        $foreign = DirectConversation::create(['user_one_id' => $other->id, 'user_two_id' => User::factory()->create()->id]);
        $response = $this->actingAs($user, 'sanctum')->getJson('/api/realtime/token')->assertOk();
        $capability = json_decode($response->json('capability'), true);
        $this->assertSame(['subscribe'], $capability['user:'.$user->id.':notifications']);
        $this->assertSame(['subscribe'], $capability['direct:'.$own->id.':messages']);
        $this->assertArrayNotHasKey('direct:'.$foreign->id.':messages', $capability);
        $this->assertArrayNotHasKey('user:'.$other->id.':notifications', $capability);
        $this->assertArrayNotHasKey('*', $capability);
    }

    public function test_pet_interest_is_refused_for_adopted_family_reserved_and_own_pets(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');
        foreach ([['status' => 'adopted'], ['status' => 'in_process'], ['ownership_kind' => 'guardian'], ['owner_id' => $user->id]] as $attributes) {
            $pet = Pet::factory()->create($attributes);
            $this->getJson('/api/pets/'.$pet->id)->assertOk()->assertJsonPath('data.can_adopt', false);
            $this->postJson('/api/pets/'.$pet->id.'/adoptions')->assertUnprocessable();
        }
        $this->assertDatabaseCount('adoptions', 0);
        $available = Pet::factory()->create();
        $this->getJson('/api/pets/'.$available->id)->assertJsonPath('data.can_adopt', true);
        $this->postJson('/api/pets/'.$available->id.'/adoptions')->assertCreated();
        $this->assertDatabaseHas('adoptions', ['pet_id' => $available->id, 'user_id' => $user->id]);
    }

    public function test_primary_owner_can_delete_adopted_pet_but_another_user_cannot(): void
    {
        $owner = User::factory()->create();
        $pet = Pet::factory()->create(['owner_id' => $owner->id, 'ownership_kind' => 'adoption', 'status' => 'adopted']);
        $post = $owner->profilePosts()->create(['pet_id' => $pet->id, 'body' => 'My adoption memory']);
        $this->actingAs(User::factory()->create(), 'sanctum')->deleteJson('/api/profile/pets/'.$pet->id)->assertForbidden();
        $this->assertModelExists($pet);
        $this->actingAs($owner, 'sanctum')->deleteJson('/api/profile/pets/'.$pet->id)->assertNoContent();
        $this->assertModelMissing($pet);
        $this->assertModelExists($post);
    }

    public function test_pet_with_scheduled_pickup_requires_cancellation_before_deletion(): void
    {
        $owner = User::factory()->create();
        $pet = Pet::factory()->create(['owner_id' => $owner->id, 'status' => 'in_process']);
        $this->adoption(User::factory()->create(), $pet, ['status' => 'approved', 'pickup_at' => now()->addDay()]);
        $this->actingAs($owner, 'sanctum')->deleteJson('/api/profile/pets/'.$pet->id)->assertConflict()->assertJsonPath('message', 'Cancele a retirada agendada antes de excluir este pet.');
        $this->assertModelExists($pet);
    }
}
