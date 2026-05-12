<?php

namespace Tests\Feature\Api\V1;

use App\Enums\BillingProvider;
use App\Enums\SavedPlaceCategory;
use App\Enums\TripMemberRole;
use App\Enums\TripPlaceSource;
use App\Models\Location;
use App\Models\SavedPlace;
use App\Models\SubscriptionPlan;
use App\Models\Trip;
use App\Models\TripMember;
use App\Models\TripPlace;
use App\Models\User;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SubscriptionPlanSeeder::class);
        config()->set('services.revenuecat.webhook_secret', 'testing-revenuecat-secret');
    }

    public function test_plans_endpoint_returns_catalog_with_current_plan_context(): void
    {
        $user = User::factory()->create();

        $this->getJson('/api/v1/plans', $this->authHeaders($user))
            ->assertOk()
            ->assertJsonPath('meta.count', 2)
            ->assertJsonPath('meta.current_plan_code', 'free')
            ->assertJsonPath('data.0.code', 'free')
            ->assertJsonPath('data.0.is_current', true)
            ->assertJsonPath('data.1.code', 'premium')
            ->assertJsonPath('data.1.is_recommended', true);
    }

    public function test_subscription_endpoint_defaults_to_free_entitlements_and_usage(): void
    {
        $user = User::factory()->create();
        $this->savedPlace($user, 'Tokyo Coffee', 'Tokyo', 35.6895, 139.6917);

        $this->getJson('/api/v1/subscription', $this->authHeaders($user))
            ->assertOk()
            ->assertJsonPath('data.subscription', null)
            ->assertJsonPath('data.plan.code', 'free')
            ->assertJsonPath('data.entitlements.enhanced_ai', false)
            ->assertJsonPath('data.usage.saved_places.used', 1)
            ->assertJsonPath('data.usage.saved_places.limit', 50)
            ->assertJsonPath('data.paywall.should_show', true);
    }

    public function test_restore_endpoint_records_request_without_trusting_client_state(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/v1/subscription/restore', [
            'provider' => BillingProvider::RevenueCat->value,
            'provider_app_user_id' => $user->uuid,
            'provider_product_id' => 'adam_travel_premium',
            'receipt_reference' => 'receipt-demo-123',
            'device_platform' => 'ios',
            'metadata' => [
                'source' => 'settings',
            ],
        ], $this->authHeaders($user))
            ->assertStatus(202)
            ->assertJsonPath('meta.refresh_requested', true)
            ->assertJsonPath('data.plan.code', 'free');

        $this->assertDatabaseHas('subscription_events', [
            'user_id' => $user->id,
            'provider' => BillingProvider::RevenueCat->value,
            'event_type' => 'restore_requested',
        ]);

        $this->assertDatabaseMissing('subscriptions', [
            'user_id' => $user->id,
        ]);
    }

    public function test_signed_revenuecat_webhook_syncs_active_subscription_and_premium_entitlements(): void
    {
        $user = User::factory()->create();

        $payload = $this->revenueCatPayload($user, [
            'type' => 'INITIAL_PURCHASE',
            'product_id' => 'adam_travel_premium',
            'period_type' => 'normal',
            'transaction_id' => 'txn-premium-001',
            'original_transaction_id' => 'orig-premium-001',
        ]);

        $this->postRevenueCatWebhook($payload)
            ->assertStatus(202)
            ->assertJsonPath('data.user_id', $user->id);

        $this->getJson('/api/v1/subscription', $this->authHeaders($user))
            ->assertOk()
            ->assertJsonPath('data.subscription.status', 'active')
            ->assertJsonPath('data.plan.code', 'premium')
            ->assertJsonPath('data.entitlements.enhanced_ai', true)
            ->assertJsonPath('data.usage.offline_packages.limit', 20);
    }

    public function test_saved_place_creation_enforces_current_plan_limit(): void
    {
        $freePlan = SubscriptionPlan::query()->where('code', 'free')->firstOrFail();
        $freePlan->update([
            'features_json' => [
                'saved_places_limit' => 1,
                'offline_packages_limit' => 1,
                'enhanced_ai' => false,
            ],
        ]);

        $user = User::factory()->create();
        $this->savedPlace($user, 'First Place', 'Tokyo', 35.6895, 139.6917);

        $this->postJson('/api/v1/saved-places', [
            'category' => SavedPlaceCategory::Activity->value,
            'title_override' => 'Second Place',
            'location' => [
                'name' => 'Second Place',
                'city' => 'Tokyo',
                'country_code' => 'JP',
                'latitude' => 35.6800,
                'longitude' => 139.7600,
            ],
        ], $this->authHeaders($user))
            ->assertStatus(422)
            ->assertJsonPath('errors.subscription.0', 'Your current plan has reached its saved places limit.');
    }

    public function test_premium_subscription_allows_multiple_offline_packages(): void
    {
        $user = User::factory()->create();
        $tripOne = $this->ownedTrip($user, [
            'title' => 'Japan Route',
            'start_date' => '2027-03-10',
            'end_date' => '2027-03-12',
        ]);
        $tripTwo = $this->ownedTrip($user, [
            'title' => 'Korea Route',
            'start_date' => '2027-04-10',
            'end_date' => '2027-04-12',
        ]);

        $this->tripPlaceForTrip($tripOne, $user, 'Tokyo Stay', 'Tokyo', 35.6895, 139.6917);
        $this->tripPlaceForTrip($tripTwo, $user, 'Seoul Stay', 'Seoul', 37.5665, 126.9780, 'KR');

        $payload = $this->revenueCatPayload($user, [
            'type' => 'INITIAL_PURCHASE',
            'product_id' => 'adam_travel_premium',
            'transaction_id' => 'txn-premium-002',
            'original_transaction_id' => 'orig-premium-002',
        ]);

        $this->postRevenueCatWebhook($payload)->assertStatus(202);

        $headers = $this->authHeaders($user);

        $this->postJson("/api/v1/offline/packages/trips/{$tripOne->id}", [], $headers)
            ->assertCreated();

        $this->postJson("/api/v1/offline/packages/trips/{$tripTwo->id}", [], $headers)
            ->assertCreated();

        $this->getJson('/api/v1/subscription', $headers)
            ->assertOk()
            ->assertJsonPath('data.plan.code', 'premium')
            ->assertJsonPath('data.usage.offline_packages.used', 2);
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

    protected function savedPlace(
        User $owner,
        string $name,
        string $city,
        float $latitude,
        float $longitude,
        string $countryCode = 'JP',
    ): SavedPlace {
        $location = Location::factory()->create([
            'name' => $name,
            'city' => $city,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'country_code' => $countryCode,
        ]);

        return SavedPlace::factory()->for($owner)->for($location)->create([
            'category' => SavedPlaceCategory::Activity->value,
            'title_override' => $name,
        ]);
    }

    protected function tripPlaceForTrip(
        Trip $trip,
        User $owner,
        string $name,
        string $city,
        float $latitude,
        float $longitude,
        string $countryCode = 'JP',
    ): TripPlace {
        $savedPlace = $this->savedPlace($owner, $name, $city, $latitude, $longitude, $countryCode);

        return TripPlace::query()->create([
            'trip_id' => $trip->id,
            'saved_place_id' => $savedPlace->id,
            'added_by_user_id' => $owner->id,
            'source' => TripPlaceSource::SavedPlace,
            'trip_category' => SavedPlaceCategory::Activity->value,
            'notes' => null,
            'is_removed' => false,
            'version' => 1,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function revenueCatPayload(User $user, array $overrides = []): array
    {
        $base = [
            'api_version' => '1.0',
            'event' => [
                'type' => 'INITIAL_PURCHASE',
                'app_user_id' => $user->uuid,
                'product_id' => 'adam_travel_premium',
                'original_transaction_id' => 'orig-default-001',
                'transaction_id' => 'txn-default-001',
                'store' => 'app_store',
                'period_type' => 'normal',
                'event_timestamp_ms' => now()->getTimestampMs(),
                'purchased_at_ms' => now()->subMinute()->getTimestampMs(),
                'expiration_at_ms' => now()->addMonth()->getTimestampMs(),
                'entitlement_ids' => ['premium'],
            ],
        ];

        $base['event'] = array_merge($base['event'], $overrides);

        return $base;
    }

    protected function postRevenueCatWebhook(array $payload)
    {
        $encoded = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $encoded, (string) config('services.revenuecat.webhook_secret'));

        return $this->call(
            'POST',
            '/api/v1/billing/webhooks/revenuecat',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_REVENUECAT_SIGNATURE' => $signature,
            ],
            $encoded,
        );
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
