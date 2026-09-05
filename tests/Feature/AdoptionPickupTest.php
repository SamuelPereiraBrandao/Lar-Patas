<?php

namespace Tests\Feature;

use App\Jobs\PublishAblyNotification;
use App\Models\Adoption;
use App\Models\Pet;
use App\Models\Role;
use App\Models\User;
use App\Models\UserNotification;
use App\Notifications\AdoptionPickupScheduled;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdoptionPickupTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['name' => 'admin'], ['label' => 'Admin']));

        return $admin;
    }

    private function interest(User $user, Pet $pet): Adoption
    {
        return $pet->adoptions()->create(['user_id' => $user->id, 'applicant_name' => $user->name, 'email' => $user->email, 'phone' => '11999999999', 'housing_type' => 'Casa', 'has_other_pets' => false, 'message' => 'Quero adotar.', 'status' => 'pending']);
    }

    /** @return array<string, string> */
    private function pickupData(): array
    {
        return ['pickup_at' => now()->addDay()->toIso8601String(), 'pickup_timezone' => 'America/Sao_Paulo', 'pickup_location' => 'Sede Centro, Rua das Flores, 123', 'pickup_message' => 'Traga uma caixa de transporte.'];
    }

    public function test_schedule_notifies_adopter_with_private_code_without_transferring_pet(): void
    {
        Notification::fake();
        Queue::fake([PublishAblyNotification::class]);
        $this->travelTo(now()->setDate(2026, 9, 5)->setTime(15, 0));
        $user = User::factory()->create();
        $pet = Pet::factory()->create(['status' => 'available', 'owner_id' => null, 'ownership_kind' => 'adoption']);
        $adoption = $this->interest($user, $pet);
        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')->postJson('/api/adoptions/'.$adoption->id.'/pickup', $this->pickupData())->assertOk()->assertJsonPath('data.status', 'approved')->assertJsonMissingPath('data.pickup_code');

        $adoption->refresh();
        $this->assertDatabaseHas('pets', ['id' => $pet->id, 'status' => 'in_process', 'owner_id' => null]);
        $this->assertDatabaseHas('adoptions', ['id' => $adoption->id, 'scheduled_by' => $admin->id, 'released_at' => null, 'pickup_at' => '2026-09-06 15:00:00']);
        $this->assertNotSame($adoption->pickup_code, $adoption->getRawOriginal('pickup_code'));
        $this->assertDatabaseCount('profile_posts', 0);
        $notice = UserNotification::where('user_id', $user->id)->sole();
        $this->assertStringContainsString($adoption->pickup_code, $notice->body);
        $this->assertStringContainsString('06/09/2026 12:00', $notice->body);
        Notification::assertSentTo($user, AdoptionPickupScheduled::class, function (AdoptionPickupScheduled $notification) use ($user, $adoption): bool {
            $mail = $notification->toMail($user);
            $html = view($mail->view['html'], $mail->viewData)->render();
            $text = view($mail->view['text'], $mail->viewData)->render();
            $this->assertStringContainsString($adoption->pickup_code, $html);
            $this->assertStringContainsString('Traga uma caixa de transporte.', $html);
            $this->assertStringContainsString(route('adoptions.pickup-page'), $html);
            $this->assertStringContainsString('06/09/2026 às 12:00', $html);
            $this->assertStringContainsString($adoption->pickup_code, $text);

            return $notification->afterCommit === true;
        });
        Queue::assertPushed(PublishAblyNotification::class, 1);
        $this->getJson('/api/admin/pets')->assertOk()->assertJsonMissingPath('data.0.adoptions.0.pickup_code');
        $this->actingAs($user, 'sanctum')->getJson('/api/dashboards/receiver')->assertOk()->assertJsonPath('data.0.verification_code', $adoption->pickup_code);
        $this->getJson('/api/profile/social')->assertOk()->assertJsonCount(0, 'my_pets');
        $this->actingAs(User::factory()->create(), 'sanctum')->getJson('/api/dashboards/receiver')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_release_transfers_ownership_and_publishes_adoption_only_once(): void
    {
        Notification::fake();
        Queue::fake([PublishAblyNotification::class]);
        $user = User::factory()->create(['city' => 'Blumenau', 'state' => 'SC']);
        $previousOwner = User::factory()->create();
        $pet = Pet::factory()->create(['owner_id' => $previousOwner->id, 'status' => 'available', 'ownership_kind' => 'adoption', 'image_path' => 'pets/mari.jpg']);
        $adoption = $this->interest($user, $pet);
        $other = $this->interest(User::factory()->create(), $pet);
        $pet->caretakers()->attach($previousOwner->id, ['status' => 'accepted']);
        $admin = $this->admin();
        $this->actingAs($admin, 'sanctum')->postJson('/api/adoptions/'.$adoption->id.'/pickup', $this->pickupData())->assertOk();
        $code = $adoption->fresh()->pickup_code;

        $this->postJson('/api/adoptions/'.$adoption->id.'/release', ['code' => $code])->assertOk()->assertJsonMissingPath('data.pickup_code');

        $this->assertDatabaseHas('pets', ['id' => $pet->id, 'owner_id' => $user->id, 'status' => 'adopted', 'ownership_kind' => 'adoption', 'city' => 'Blumenau', 'state' => 'SC']);
        $this->assertNotNull($adoption->fresh()->released_at);
        $this->assertDatabaseHas('adoptions', ['id' => $adoption->id, 'released_by' => $admin->id, 'pickup_code' => null]);
        $this->assertDatabaseHas('adoptions', ['id' => $other->id, 'status' => 'rejected']);
        $this->assertDatabaseCount('pet_caretakers', 0);
        $this->assertDatabaseHas('profile_posts', ['user_id' => $user->id, 'pet_id' => $pet->id, 'image_path' => 'pets/mari.jpg']);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $user->id, 'type' => 'adoption_completed']);
        Queue::assertPushed(PublishAblyNotification::class, 2);
        $this->postJson('/api/adoptions/'.$adoption->id.'/release', ['code' => $code])->assertConflict();
        $this->assertDatabaseCount('profile_posts', 1);
        $this->assertDatabaseCount('user_notifications', 2);
        $this->actingAs($user, 'sanctum')->getJson('/api/profile/social')->assertOk()->assertJsonPath('my_pets.0.id', $pet->id)->assertJsonPath('posts.0.pet_id', $pet->id);
        $this->getJson('/api/dashboards/receiver')->assertOk()->assertJsonMissingPath('data.0.verification_code');
    }

    public function test_wrong_code_returns_422_without_transfer_or_publication(): void
    {
        Queue::fake([PublishAblyNotification::class]);
        $pet = Pet::factory()->create(['status' => 'in_process', 'owner_id' => null]);
        $adoption = $this->interest(User::factory()->create(), $pet);
        $adoption->update(['status' => 'approved', 'pickup_at' => now(), 'pickup_code' => '123456']);

        $this->actingAs($this->admin(), 'sanctum')->postJson('/api/adoptions/'.$adoption->id.'/release', ['code' => '654321'])->assertUnprocessable()->assertJsonPath('errors.code.0', 'Código inválido. Confira o código apresentado pelo adotante.');

        $this->assertDatabaseHas('pets', ['id' => $pet->id, 'owner_id' => null, 'status' => 'in_process']);
        $this->assertDatabaseHas('adoptions', ['id' => $adoption->id, 'released_at' => null]);
        $this->assertDatabaseCount('profile_posts', 0);
        Queue::assertNotPushed(PublishAblyNotification::class);
    }

    public function test_schedule_conflicts_do_not_send_duplicate_notifications(): void
    {
        Notification::fake();
        Queue::fake([PublishAblyNotification::class]);
        $pet = Pet::factory()->create(['status' => 'available']);
        $adoption = $this->interest(User::factory()->create(), $pet);
        $other = $this->interest(User::factory()->create(), $pet);
        $this->actingAs($this->admin(), 'sanctum')->postJson('/api/adoptions/'.$adoption->id.'/pickup', $this->pickupData())->assertOk();

        $this->postJson('/api/adoptions/'.$adoption->id.'/pickup', $this->pickupData())->assertConflict();
        $this->postJson('/api/adoptions/'.$other->id.'/pickup', $this->pickupData())->assertConflict();

        $this->assertDatabaseHas('adoptions', ['id' => $other->id, 'status' => 'pending']);
        $this->assertDatabaseCount('user_notifications', 1);
        Notification::assertCount(1);
        Queue::assertPushed(PublishAblyNotification::class, 1);
    }

    public function test_only_admin_can_schedule_or_release_and_legacy_approval_is_blocked(): void
    {
        Notification::fake();
        $pet = Pet::factory()->create(['status' => 'available', 'owner_id' => null]);
        $user = User::factory()->create();
        $adoption = $this->interest($user, $pet);
        $this->postJson('/api/adoptions/'.$adoption->id.'/pickup', $this->pickupData())->assertUnauthorized();
        $this->postJson('/api/adoptions/'.$adoption->id.'/release', ['code' => '123456'])->assertUnauthorized();
        $this->actingAs($user, 'sanctum')->postJson('/api/adoptions/'.$adoption->id.'/pickup', $this->pickupData())->assertForbidden();
        $this->postJson('/api/adoptions/'.$adoption->id.'/release', ['code' => '123456'])->assertForbidden();
        $this->patchJson('/api/adoptions/'.$adoption->id, ['status' => 'approved'])->assertForbidden();
        $this->actingAs($this->admin(), 'sanctum')->patchJson('/api/adoptions/'.$adoption->id, ['status' => 'approved'])->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->assertDatabaseHas('pets', ['id' => $pet->id, 'owner_id' => null, 'status' => 'available']);
        Notification::assertNothingSent();
    }

    /** @return array<string, array{string, mixed}> */
    public static function invalidPickup(): array
    {
        return ['past date' => ['pickup_at', '2020-01-01T10:00:00Z'], 'missing date' => ['pickup_at', null], 'missing location' => ['pickup_location', ''], 'missing message' => ['pickup_message', ''], 'invalid timezone' => ['pickup_timezone', 'Invalid/Zone']];
    }

    #[DataProvider('invalidPickup')]
    public function test_invalid_schedule_returns_422_without_changes(string $field, mixed $value): void
    {
        Notification::fake();
        $pet = Pet::factory()->create(['status' => 'available']);
        $adoption = $this->interest(User::factory()->create(), $pet);

        $this->actingAs($this->admin(), 'sanctum')->postJson('/api/adoptions/'.$adoption->id.'/pickup', [...$this->pickupData(), $field => $value])->assertUnprocessable()->assertJsonValidationErrors($field);

        $this->assertDatabaseHas('adoptions', ['id' => $adoption->id, 'status' => 'pending', 'pickup_at' => null]);
        $this->assertDatabaseHas('pets', ['id' => $pet->id, 'status' => 'available']);
        Notification::assertNothingSent();
    }

    public function test_approved_pickup_cannot_be_deleted_or_reset(): void
    {
        $user = User::factory()->create();
        $pet = Pet::factory()->create(['status' => 'in_process']);
        $adoption = $this->interest($user, $pet);
        $adoption->update(['status' => 'approved', 'pickup_at' => now(), 'pickup_code' => '123456']);

        $this->actingAs($user, 'sanctum')->deleteJson('/api/adoptions/'.$adoption->id)->assertConflict();
        $this->actingAs($this->admin(), 'sanctum')->patchJson('/api/adoptions/'.$adoption->id, ['status' => 'pending'])->assertConflict();

        $this->assertDatabaseHas('adoptions', ['id' => $adoption->id, 'status' => 'approved']);
    }

    public function test_pending_interest_cannot_be_released(): void
    {
        $pet = Pet::factory()->create(['status' => 'available']);
        $adoption = $this->interest(User::factory()->create(), $pet);

        $this->actingAs($this->admin(), 'sanctum')->postJson('/api/adoptions/'.$adoption->id.'/release', ['code' => '123456'])->assertConflict();

        $this->assertDatabaseHas('pets', ['id' => $pet->id, 'status' => 'available']);
        $this->assertDatabaseCount('profile_posts', 0);
    }

    public function test_unavailable_pet_and_deleted_applicant_cannot_be_scheduled(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $pet = Pet::factory()->create(['status' => 'adopted']);
        $adoption = $this->interest($user, $pet);
        $this->actingAs($this->admin(), 'sanctum')->postJson('/api/adoptions/'.$adoption->id.'/pickup', $this->pickupData())->assertConflict();
        $pet->update(['status' => 'available', 'ownership_kind' => 'guardian']);
        $this->postJson('/api/adoptions/'.$adoption->id.'/pickup', $this->pickupData())->assertConflict();
        $pet->update(['ownership_kind' => 'adoption']);
        $user->delete();
        $this->postJson('/api/adoptions/'.$adoption->id.'/pickup', $this->pickupData())->assertConflict();
        $this->assertDatabaseHas('adoptions', ['id' => $adoption->id, 'status' => 'pending', 'pickup_code' => null]);
        Notification::assertNothingSent();
    }

    public function test_release_requires_six_digit_code(): void
    {
        $pet = Pet::factory()->create(['status' => 'in_process']);
        $adoption = $this->interest(User::factory()->create(), $pet);
        $adoption->update(['status' => 'approved', 'pickup_at' => now(), 'pickup_code' => '123456']);

        $this->actingAs($this->admin(), 'sanctum')->postJson('/api/adoptions/'.$adoption->id.'/release', ['code' => '123'])->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->postJson('/api/adoptions/'.$adoption->id.'/release', [])->assertUnprocessable()->assertJsonValidationErrors('code');

        $this->assertDatabaseHas('adoptions', ['id' => $adoption->id, 'released_at' => null]);
    }

    public function test_release_rate_limits_incorrect_codes(): void
    {
        $pet = Pet::factory()->create(['status' => 'in_process']);
        $adoption = $this->interest(User::factory()->create(), $pet);
        $adoption->update(['status' => 'approved', 'pickup_at' => now(), 'pickup_code' => '123456']);
        $this->actingAs($this->admin(), 'sanctum');
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/adoptions/'.$adoption->id.'/release', ['code' => '654321'])->assertUnprocessable();
        }

        $this->postJson('/api/adoptions/'.$adoption->id.'/release', ['code' => '654321'])->assertTooManyRequests();

        $this->assertDatabaseCount('profile_posts', 0);
    }
}
