<?php

namespace Tests\Feature\Api\V1;

use App\Enums\ImportSourceType;
use App\Enums\ImportStatus;
use App\Enums\TripMemberRole;
use App\Models\Import;
use App\Models\ImportCandidate;
use App\Models\Location;
use App\Models\RecentSearch;
use App\Models\SavedPlace;
use App\Models\SavedPlaceCollection;
use App\Models\Trip;
use App\Models\TripMember;
use App\Models\User;
use App\Models\UserNotification;
use Database\Seeders\AppSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            AppSettingSeeder::class,
        ]);
    }

    public function test_dashboard_search_notifications_and_map_filters_support_home_screen(): void
    {
        $user = User::factory()->create([
            'password' => 'secret123!',
        ]);
        $collection = SavedPlaceCollection::query()->create([
            'user_id' => $user->id,
            'name' => 'Tokyo 2027',
            'color_hex' => '#0F9FB2',
        ]);

        $nearLocation = Location::factory()->create([
            'name' => 'Sushi Saito',
            'latitude' => 35.6804,
            'longitude' => 139.7690,
            'is_moderated_hidden' => false,
        ]);
        $farLocation = Location::factory()->create([
            'name' => 'Kyoto Temple',
            'latitude' => 35.0116,
            'longitude' => 135.7681,
            'is_moderated_hidden' => false,
        ]);

        $nearPlace = SavedPlace::factory()->for($user)->create([
            'location_id' => $nearLocation->id,
            'title_override' => 'Sushi Saito',
            'category' => 'restaurant',
            'region_label' => 'Tokyo 2027',
            'saved_place_collection_id' => $collection->id,
            'is_favorite' => true,
        ]);
        SavedPlace::factory()->for($user)->create([
            'location_id' => $farLocation->id,
            'title_override' => 'Kyoto Temple',
            'category' => 'activity',
            'region_label' => null,
            'saved_place_collection_id' => null,
            'is_favorite' => false,
        ]);

        RecentSearch::query()->create([
            'user_id' => $user->id,
            'query' => 'Coffee shops near me',
            'result_count' => 4,
            'used_at' => now()->subMinutes(10),
        ]);

        $todayNotification = UserNotification::query()->create([
            'user_id' => $user->id,
            'type' => 'trip_update',
            'title' => 'Trip itinerary updated',
            'body' => 'Your Bali trip itinerary has new activity suggestions.',
            'tag' => 'Trips',
            'sent_at' => now()->subHour(),
        ]);
        UserNotification::query()->create([
            'user_id' => $user->id,
            'type' => 'welcome',
            'title' => 'Welcome aboard',
            'body' => 'Your profile is ready to explore.',
            'tag' => 'Welcome',
            'sent_at' => now()->subDay(),
        ]);

        $headers = $this->authHeaders($user);

        $this->getJson('/api/v1/dashboard?latitude=35.6804&longitude=139.7690&radius_meters=1200', $headers)
            ->assertOk()
            ->assertJsonPath('data.quick_actions.0.key', 'add_pin')
            ->assertJsonPath('data.notifications.unread_count', 2)
            ->assertJsonPath('data.smart_banner.type', 'nearby_saved_places')
            ->assertJsonPath('data.smart_banner.nearby_places.0.saved_place.id', $nearPlace->id)
            ->assertJsonPath('data.filters.collections.0.id', $collection->id);

        $this->getJson('/api/v1/home/search?latitude=35.6804&longitude=139.7690&radius_meters=1200', $headers)
            ->assertOk()
            ->assertJsonCount(1, 'data.recent_searches')
            ->assertJsonCount(2, 'data.trending_now')
            ->assertJsonPath('data.nearby_places.0.saved_place.id', $nearPlace->id);

        $this->getJson('/api/v1/home/search?q=Sushi', $headers)
            ->assertOk()
            ->assertJsonCount(1, 'data.results')
            ->assertJsonPath('data.results.0.id', $nearPlace->id);

        $this->postJson('/api/v1/home/searches', [
            'q' => 'Tokyo food',
            'result_count' => 3,
        ], $headers)
            ->assertCreated()
            ->assertJsonPath('data.query', 'Tokyo food');

        $this->getJson('/api/v1/notifications', $headers)
            ->assertOk()
            ->assertJsonPath('data.summary.unread_count', 2)
            ->assertJsonPath('data.groups.0.label', 'Today');

        $this->postJson("/api/v1/notifications/{$todayNotification->id}/read", [], $headers)
            ->assertOk()
            ->assertJsonPath('data.is_read', true);

        $this->postJson('/api/v1/notifications/read-all', [], $headers)
            ->assertOk()
            ->assertJsonPath('data.updated_count', 1);

        $this->deleteJson('/api/v1/home/searches', [], $headers)
            ->assertOk();

        $this->getJson('/api/v1/map/pins?saved_place_collection_id='.$collection->id.'&latitude=35.6804&longitude=139.7690&radius_meters=1200', $headers)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $nearPlace->id);
    }

    public function test_saved_place_detail_collections_and_trip_links_support_home_location_flow(): void
    {
        $user = User::factory()->create([
            'email' => 'jane@example.com',
            'password' => 'secret123!',
        ]);

        $location = Location::factory()->create([
            'name' => 'Colosseum',
            'city' => 'Rome',
            'metadata' => [
                'hero_image_url' => 'https://cdn.example.com/colosseum.jpg',
            ],
            'is_moderated_hidden' => false,
        ]);

        $import = Import::query()->create([
            'user_id' => $user->id,
            'source_type' => ImportSourceType::Url,
            'source_url' => 'https://maps.google.com/example',
            'source_host' => 'maps.google.com',
            'raw_text' => 'Colosseum Rome',
            'normalized_text' => 'colosseum rome',
            'status' => ImportStatus::Completed,
        ]);

        ImportCandidate::query()->create([
            'import_id' => $import->id,
            'candidate_rank' => 1,
            'place_name' => 'Colosseum',
            'category' => 'attraction',
            'city' => 'Rome',
            'country' => 'Italy',
            'summary' => 'Ancient amphitheatre and one of Rome\'s most iconic landmarks.',
            'confidence_score' => 0.98,
            'selected_at' => now(),
            'metadata' => [
                'hero_image_url' => 'https://cdn.example.com/colosseum-candidate.jpg',
            ],
        ]);

        $savedPlace = SavedPlace::factory()->for($user)->create([
            'location_id' => $location->id,
            'import_id' => $import->id,
            'title_override' => 'Colosseum',
            'category' => 'activity',
            'region_label' => null,
            'saved_place_collection_id' => null,
            'is_favorite' => true,
        ]);

        $trip = $this->ownedTrip($user, [
            'title' => 'Bali Retreat',
            'cover_image_url' => 'https://cdn.example.com/bali-retreat.jpg',
        ]);

        $headers = $this->authHeaders($user);

        $this->getJson("/api/v1/saved-places/{$savedPlace->id}", $headers)
            ->assertOk()
            ->assertJsonPath('data.preview_summary', 'Ancient amphitheatre and one of Rome\'s most iconic landmarks.')
            ->assertJsonPath('data.hero_image_url', 'https://cdn.example.com/colosseum.jpg')
            ->assertJsonPath('data.actions.can_add_to_trip', true);

        $this->postJson('/api/v1/saved-place-collections', [
            'name' => 'Europe',
        ], $headers)
            ->assertCreated()
            ->assertJsonPath('data.name', 'Europe');

        $collection = SavedPlaceCollection::query()->where('user_id', $user->id)->where('name', 'Europe')->firstOrFail();

        $this->postJson("/api/v1/saved-places/{$savedPlace->id}/categorize", [
            'saved_place_collection_id' => $collection->id,
        ], $headers)
            ->assertOk()
            ->assertJsonPath('data.saved_place_collection_id', $collection->id)
            ->assertJsonPath('data.region_label', 'Europe');

        $this->getJson('/api/v1/saved-place-collections?saved_place_id='.$savedPlace->id, $headers)
            ->assertOk()
            ->assertJsonPath('data.selected_saved_place_collection_id', $collection->id);

        $this->getJson("/api/v1/saved-places/{$savedPlace->id}/trip-options", $headers)
            ->assertOk()
            ->assertJsonPath('data.0.id', $trip->id)
            ->assertJsonPath('data.0.can_add', true);

        $this->postJson("/api/v1/saved-places/{$savedPlace->id}/trip-links", [
            'trip_id' => $trip->id,
            'notes' => 'Add this to the first day.',
        ], $headers)
            ->assertCreated()
            ->assertJsonPath('data.trip_id', $trip->id)
            ->assertJsonPath('data.saved_place_id', $savedPlace->id);

        $this->assertDatabaseHas('trip_places', [
            'trip_id' => $trip->id,
            'saved_place_id' => $savedPlace->id,
        ]);

        $this->getJson("/api/v1/saved-places/{$savedPlace->id}", $headers)
            ->assertOk()
            ->assertJsonCount(1, 'data.trip_links');
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
