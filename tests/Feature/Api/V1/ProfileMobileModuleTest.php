<?php

namespace Tests\Feature\Api\V1;

use App\Enums\BillingProvider;
use App\Enums\FriendRequestStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\TripInviteStatus;
use App\Enums\TripMemberRole;
use App\Enums\TripStatus;
use App\Models\FriendRequest;
use App\Models\Friendship;
use App\Models\ItineraryDay;
use App\Models\ItineraryItem;
use App\Models\SavedPlace;
use App\Models\SubscriptionPlan;
use App\Models\Trip;
use App\Models\TripInvite;
use App\Models\TripMember;
use App\Models\TripPlace;
use App\Models\User;
use App\Models\UserSubscription;
use Database\Seeders\AppSettingSeeder;
use Database\Seeders\CmsPageSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileMobileModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            SubscriptionPlanSeeder::class,
            AppSettingSeeder::class,
            CmsPageSeeder::class,
        ]);
    }

    public function test_profile_dashboard_and_settings_screen_payloads_are_loaded(): void
    {
        $user = User::factory()->create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'secret123!',
        ]);
        SavedPlace::factory()->for($user)->count(2)->create();

        $friend = User::factory()->create();
        Friendship::query()->create([
            'user_id' => $user->id,
            'friend_user_id' => $friend->id,
            'connected_at' => now(),
        ]);

        FriendRequest::query()->create([
            'sender_user_id' => User::factory()->create()->id,
            'recipient_user_id' => $user->id,
            'status' => FriendRequestStatus::Pending,
        ]);

        $owner = User::factory()->create();
        $trip = $this->ownedTrip($owner, [
            'title' => 'Japan Escape',
        ]);

        TripInvite::query()->create([
            'trip_id' => $trip->id,
            'invited_by_user_id' => $owner->id,
            'email' => 'jane@example.com',
            'role' => TripMemberRole::Viewer,
            'status' => TripInviteStatus::Pending,
            'expires_at' => now()->addDays(3),
        ]);

        $headers = $this->authHeaders($user);

        $this->getJson('/api/v1/profile', $headers)
            ->assertOk()
            ->assertJsonPath('data.user.email', 'jane@example.com')
            ->assertJsonPath('data.stats.saved_places_count', 2)
            ->assertJsonPath('data.activity.friends_count', 1)
            ->assertJsonPath('data.activity.trip_invitations_count', 1)
            ->assertJsonPath('data.subscription.label', 'Upgrade to Premium');

        $this->getJson('/api/v1/settings', $headers)
            ->assertOk()
            ->assertJsonPath('data.distance_unit', 'km')
            ->assertJsonPath('data.account.user.email', 'jane@example.com')
            ->assertJsonPath('data.app.version', '2.4.1')
            ->assertJsonCount(3, 'data.pages');
    }

    public function test_invitation_tabs_support_trip_acceptance_and_friend_acceptance(): void
    {
        $user = User::factory()->create([
            'email' => 'invitee@example.com',
            'password' => 'secret123!',
        ]);
        $owner = User::factory()->create();
        $trip = $this->ownedTrip($owner, [
            'title' => 'Bali Adventure 2026',
            'start_date' => '2026-03-15',
            'end_date' => '2026-03-28',
        ]);

        $invite = TripInvite::query()->create([
            'trip_id' => $trip->id,
            'invited_by_user_id' => $owner->id,
            'email' => 'invitee@example.com',
            'role' => TripMemberRole::Editor,
            'status' => TripInviteStatus::Pending,
            'expires_at' => now()->addDays(3),
        ]);

        FriendRequest::query()->create([
            'sender_user_id' => User::factory()->create(['name' => 'Sarah Johnson'])->id,
            'recipient_user_id' => $user->id,
            'status' => FriendRequestStatus::Pending,
        ]);
        FriendRequest::query()->create([
            'sender_user_id' => User::factory()->create(['name' => 'Marcus Lee'])->id,
            'recipient_user_id' => $user->id,
            'status' => FriendRequestStatus::Pending,
        ]);

        $headers = $this->authHeaders($user);

        $this->getJson('/api/v1/profile/invitations?tab=all', $headers)
            ->assertOk()
            ->assertJsonPath('data.counts.trips', 1)
            ->assertJsonPath('data.counts.friends', 2)
            ->assertJsonCount(1, 'data.trip_invitations')
            ->assertJsonCount(2, 'data.friend_requests');

        $this->postJson("/api/v1/profile/invitations/trips/{$invite->id}/accept", [], $headers)
            ->assertOk()
            ->assertJsonPath('data.current_user_role', TripMemberRole::Editor->value);

        $this->assertDatabaseHas('trip_members', [
            'trip_id' => $trip->id,
            'user_id' => $user->id,
            'role' => TripMemberRole::Editor->value,
        ]);

        $this->postJson('/api/v1/profile/invitations/friends/accept-all', [], $headers)
            ->assertOk()
            ->assertJsonPath('data.accepted_count', 2);

        $this->assertDatabaseCount('friendships', 4);
    }

    public function test_timeline_support_and_subscription_profile_endpoints_work_together(): void
    {
        $user = User::factory()->create([
            'password' => 'secret123!',
        ]);
        $trip = $this->ownedTrip($user, [
            'title' => 'Bali Retreat',
            'status' => TripStatus::Archived,
            'start_date' => '2025-03-02',
            'end_date' => '2025-03-14',
        ]);
        $savedPlace = SavedPlace::factory()->for($user)->create([
            'category' => 'hotel',
        ]);
        $tripPlace = TripPlace::query()->create([
            'trip_id' => $trip->id,
            'saved_place_id' => $savedPlace->id,
            'added_by_user_id' => $user->id,
            'source' => 'saved_place',
            'trip_category' => 'Hotel',
            'notes' => 'Main stay',
            'version' => 1,
        ]);
        $day = ItineraryDay::query()->create([
            'trip_id' => $trip->id,
            'day_number' => 1,
            'trip_date' => '2025-03-02',
            'title' => 'Day 1',
            'version' => 1,
        ]);
        ItineraryItem::query()->create([
            'itinerary_day_id' => $day->id,
            'trip_place_id' => $tripPlace->id,
            'scheduled_by_user_id' => $user->id,
            'source' => 'manual',
            'starts_at' => '2025-03-02 18:00:00',
            'sort_order' => 1,
            'notes' => 'Hotel check-in',
            'version' => 1,
        ]);

        $premiumPlan = SubscriptionPlan::query()->where('code', 'premium')->firstOrFail();
        UserSubscription::query()->create([
            'user_id' => $user->id,
            'subscription_plan_id' => $premiumPlan->id,
            'provider' => BillingProvider::RevenueCat->value,
            'provider_product_id' => $premiumPlan->provider_product_id,
            'status' => SubscriptionStatus::Active,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addMonth(),
            'auto_renews' => true,
            'last_synced_at' => now(),
        ]);

        $headers = $this->authHeaders($user);

        $this->getJson('/api/v1/timeline', $headers)
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Bali Retreat')
            ->assertJsonPath('data.0.timeline_status_label', 'Done');

        $this->getJson("/api/v1/timeline/{$trip->id}", $headers)
            ->assertOk()
            ->assertJsonPath('data.is_read_only', true)
            ->assertJsonPath('data.itinerary_days.0.items.0.notes', 'Hotel check-in');

        $this->getJson('/api/v1/support?q=offline', $headers)
            ->assertOk()
            ->assertJsonCount(1, 'data.faqs');

        $this->postJson('/api/v1/support-tickets', [
            'subject' => 'Need help with offline maps',
            'message' => 'My offline package is missing after reinstalling the app.',
            'priority' => 'high',
        ], $headers)
            ->assertCreated()
            ->assertJsonPath('data.subject', 'Need help with offline maps');

        $this->getJson('/api/v1/support-tickets', $headers)
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->postJson('/api/v1/subscription/checkout-preview', [
            'plan_code' => 'premium',
            'billing_cycle' => 'monthly',
        ], $headers)
            ->assertOk()
            ->assertJsonPath('data.total_today', 4.99)
            ->assertJsonPath('data.payment_method.last4', '4242');

        $this->getJson('/api/v1/subscription/activated', $headers)
            ->assertOk()
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.headline', 'Subscription Activated')
            ->assertJsonPath('data.badge', 'Premium · $4.99/month');
    }

    public function test_user_can_delete_account_with_current_password(): void
    {
        $user = User::factory()->create([
            'email' => 'delete-me@example.com',
            'password' => 'secret123!',
        ]);

        $headers = $this->authHeaders($user);

        $this->deleteJson('/api/v1/me', [
            'current_password' => 'secret123!',
        ], $headers)
            ->assertOk()
            ->assertJsonPath('message', 'Your account was permanently deleted successfully.');

        $this->assertDatabaseMissing('users', [
            'id' => $user->id,
            'email' => 'delete-me@example.com',
        ]);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => User::class,
        ]);
    }

    protected function ownedTrip(User $owner, array $overrides = []): Trip
    {
        $trip = Trip::factory()->for($owner, 'owner')->create($overrides);

        TripMember::query()->create([
            'trip_id' => $trip->id,
            'user_id' => $owner->id,
            'role' => TripMemberRole::Owner,
            'joined_at' => now(),
        ]);

        return $trip;
    }

    /**
     * @return array<string, string>
     */
    protected function authHeaders(User $user): array
    {
        $token = $user->createToken('Test Device')->plainTextToken;

        return [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ];
    }
}
