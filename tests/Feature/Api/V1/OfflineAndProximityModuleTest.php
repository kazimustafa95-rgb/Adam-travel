<?php

namespace Tests\Feature\Api\V1;

use App\Enums\SavedPlaceCategory;
use App\Enums\TripMemberRole;
use App\Models\Location;
use App\Models\OfflinePackage;
use App\Models\SavedPlace;
use App\Models\Trip;
use App\Models\TripMember;
use App\Models\TripPlace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfflineAndProximityModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_proximity_check_returns_nearby_places_and_enforces_cooldown(): void
    {
        $user = User::factory()->create();
        $headers = $this->authHeaders($user);

        $nearbyPlace = $this->savedPlace($user, 'Tokyo Coffee', 'Tokyo', 35.6896, 139.6918);
        $this->savedPlace($user, 'Far Away', 'Osaka', 34.6937, 135.5023);

        $firstResponse = $this->postJson('/api/v1/proximity/check', [
            'latitude' => 35.6895,
            'longitude' => 139.6917,
            'radius_meters' => 500,
        ], $headers);

        $firstResponse
            ->assertOk()
            ->assertJsonPath('data.should_prompt', true)
            ->assertJsonPath('meta.count', 1)
            ->assertJsonPath('data.nearby_places.0.saved_place.id', $nearbyPlace->id);

        $secondResponse = $this->postJson('/api/v1/proximity/check', [
            'latitude' => 35.6895,
            'longitude' => 139.6917,
            'radius_meters' => 500,
        ], $headers);

        $secondResponse
            ->assertOk()
            ->assertJsonPath('data.should_prompt', false)
            ->assertJsonPath('data.cooldown_active', true);
    }

    public function test_user_can_create_and_list_offline_trip_package_and_limit_is_enforced(): void
    {
        $owner = User::factory()->create();
        $trip = $this->ownedTrip($owner, [
            'title' => 'Japan Route',
            'start_date' => '2027-03-10',
            'end_date' => '2027-03-12',
        ]);
        $this->tripPlaceForTrip($trip, $owner, 'Tokyo Stay', 'Tokyo', SavedPlaceCategory::Hotel);

        $otherTrip = $this->ownedTrip($owner, [
            'title' => 'Korea Route',
            'start_date' => '2027-04-10',
            'end_date' => '2027-04-12',
        ]);
        $this->tripPlaceForTrip($otherTrip, $owner, 'Seoul Walk', 'Seoul', SavedPlaceCategory::Activity, 37.5665, 126.9780, 'KR');

        $headers = $this->authHeaders($owner);

        $createResponse = $this->postJson("/api/v1/offline/packages/trips/{$trip->id}", [], $headers);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('data.trip_id', $trip->id)
            ->assertJsonPath('data.package_scope', 'trip')
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.manifest.trip.id', $trip->id);

        $this->getJson('/api/v1/offline/packages', $headers)
            ->assertOk()
            ->assertJsonPath('meta.count', 1)
            ->assertJsonPath('data.0.trip_id', $trip->id);

        $this->postJson("/api/v1/offline/packages/trips/{$otherTrip->id}", [], $headers)
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_sync_pull_returns_changes_and_deleted_markers(): void
    {
        $user = User::factory()->create();
        $trip = $this->ownedTrip($user, [
            'start_date' => '2027-03-10',
            'end_date' => '2027-03-12',
        ]);
        $tripPlace = $this->tripPlaceForTrip($trip, $user, 'Kyoto Temple', 'Kyoto', SavedPlaceCategory::Activity, 35.0116, 135.7681);
        $deletedSavedPlace = $this->savedPlace($user, 'Delete Me', 'Kyoto', 35.0120, 135.7685);
        $deletedSavedPlace->delete();

        OfflinePackage::query()->create([
            'user_id' => $user->id,
            'trip_id' => $trip->id,
            'package_scope' => 'trip',
            'scope_reference' => $trip->slug,
            'manifest_version' => $trip->version,
            'status' => 'ready',
            'manifest_payload' => ['trip_id' => $trip->id],
            'expires_at' => now()->addDays(30),
        ]);

        $response = $this->getJson('/api/v1/sync?device_identifier=test-sync-device&device_name=QA%20Phone&device_platform=ios', $this->authHeaders($user));

        $response
            ->assertOk()
            ->assertJsonPath('data.user_preference.version', 1)
            ->assertJsonPath('data.trips.0.id', $trip->id)
            ->assertJsonPath('data.trip_places.0.id', $tripPlace->id)
            ->assertJsonPath('data.offline_packages.0.trip_id', $trip->id)
            ->assertJsonFragment(['entity' => 'saved_place', 'id' => $deletedSavedPlace->id]);
    }

    public function test_sync_push_updates_preferences_and_saved_places_and_reports_conflicts(): void
    {
        $user = User::factory()->create();
        $savedPlace = $this->savedPlace($user, 'Update Me', 'Tokyo', 35.6895, 139.6917);
        $headers = $this->authHeaders($user);

        $this->postJson('/api/v1/sync/push', [
            'device_identifier' => 'sync-device-1',
            'device_name' => 'QA Phone',
            'device_platform' => 'ios',
            'changes' => [
                [
                    'entity' => 'user_preference',
                    'action' => 'update',
                    'payload' => [
                        'offline_auto_sync' => false,
                        'theme' => 'light',
                    ],
                ],
                [
                    'entity' => 'saved_place',
                    'action' => 'update',
                    'record_id' => $savedPlace->id,
                    'version' => 1,
                    'payload' => [
                        'notes' => 'Updated offline note',
                        'is_favorite' => true,
                    ],
                ],
            ],
        ], $headers)
            ->assertOk()
            ->assertJsonPath('data.user_preference.offline_auto_sync', false)
            ->assertJsonPath('data.saved_places.0.notes', 'Updated offline note')
            ->assertJsonPath('data.saved_places.0.is_favorite', true);

        $savedPlace->refresh();

        $this->assertSame('Updated offline note', $savedPlace->notes);
        $this->assertTrue($savedPlace->is_favorite);

        $this->postJson('/api/v1/sync/push', [
            'device_identifier' => 'sync-device-1',
            'device_name' => 'QA Phone',
            'device_platform' => 'ios',
            'changes' => [
                [
                    'entity' => 'saved_place',
                    'action' => 'update',
                    'record_id' => $savedPlace->id,
                    'version' => 1,
                    'payload' => [
                        'notes' => 'Stale note',
                    ],
                ],
            ],
        ], $headers)
            ->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.conflicts.0.reason', 'The saved place version is stale.');
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
        SavedPlaceCategory $category,
        float $latitude = 35.6762,
        float $longitude = 139.6503,
        string $countryCode = 'JP',
    ): TripPlace {
        $savedPlace = $this->savedPlace($owner, $name, $city, $latitude, $longitude, $countryCode);
        $savedPlace->update([
            'category' => $category->value,
        ]);

        return TripPlace::query()->create([
            'trip_id' => $trip->id,
            'saved_place_id' => $savedPlace->id,
            'added_by_user_id' => $owner->id,
            'source' => 'saved_place',
            'trip_category' => $category->value,
            'notes' => null,
            'is_removed' => false,
            'version' => 1,
        ]);
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
