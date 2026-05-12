<?php

namespace Tests\Feature\Api\V1;

use App\Enums\SavedPlaceCategory;
use App\Models\Location;
use App\Models\SavedPlace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SavedPlacesModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_and_list_saved_places(): void
    {
        $user = User::factory()->create();
        $headers = $this->authHeaders($user);

        $createResponse = $this->postJson('/api/v1/saved-places', [
            'location' => [
                'name' => 'Senso-ji Temple',
                'category' => 'activity',
                'city' => 'Tokyo',
                'country_code' => 'JP',
                'latitude' => 35.7148,
                'longitude' => 139.7967,
                'provider_source' => 'manual',
            ],
            'category' => SavedPlaceCategory::Activity->value,
            'title_override' => 'Must Visit Temple',
            'notes' => 'Historic temple stop for day one.',
            'region_label' => 'Japan 2027',
            'is_favorite' => true,
            'visibility' => 'private',
        ], $headers);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'Must Visit Temple')
            ->assertJsonPath('data.location.name', 'Senso-ji Temple')
            ->assertJsonPath('data.is_favorite', true);

        $this->assertDatabaseHas('saved_places', [
            'user_id' => $user->id,
            'title_override' => 'Must Visit Temple',
            'category' => SavedPlaceCategory::Activity->value,
        ]);

        $this->getJson('/api/v1/saved-places', $headers)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.location.city', 'Tokyo');
    }

    public function test_user_can_filter_and_search_saved_places(): void
    {
        $user = User::factory()->create();
        $headers = $this->authHeaders($user);

        $tokyo = Location::factory()->create([
            'name' => 'Tokyo Skytree',
            'city' => 'Tokyo',
            'country_code' => 'JP',
        ]);
        $athens = Location::factory()->create([
            'name' => 'Acropolis Museum',
            'city' => 'Athens',
            'country_code' => 'GR',
        ]);

        SavedPlace::factory()->for($user)->for($tokyo)->create([
            'category' => SavedPlaceCategory::Activity->value,
            'region_label' => 'Japan 2027',
            'is_favorite' => true,
            'title_override' => 'Tokyo Tower Day',
        ]);
        SavedPlace::factory()->for($user)->for($athens)->create([
            'category' => SavedPlaceCategory::Restaurant->value,
            'region_label' => 'Greece',
            'is_favorite' => false,
            'title_override' => 'Museum Lunch',
        ]);

        $this->getJson('/api/v1/saved-places?category=activity&is_favorite=1', $headers)
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Tokyo Tower Day');

        $this->getJson('/api/v1/saved-places/search?q=Tokyo', $headers)
            ->assertOk()
            ->assertJsonPath('meta.count', 1)
            ->assertJsonPath('data.0.location.name', 'Tokyo Skytree');
    }

    public function test_user_can_update_and_delete_owned_saved_place(): void
    {
        $user = User::factory()->create();
        $headers = $this->authHeaders($user);

        $location = Location::factory()->create([
            'name' => 'Original Place',
            'city' => 'Paris',
        ]);

        $savedPlace = SavedPlace::factory()->for($user)->for($location)->create([
            'title_override' => 'Original Title',
            'notes' => 'Old notes',
            'category' => SavedPlaceCategory::Other->value,
            'is_favorite' => false,
        ]);

        $this->patchJson("/api/v1/saved-places/{$savedPlace->id}", [
            'title_override' => null,
            'notes' => 'Updated notes',
            'category' => SavedPlaceCategory::Restaurant->value,
            'is_favorite' => true,
        ], $headers)
            ->assertOk()
            ->assertJsonPath('data.title', 'Original Place')
            ->assertJsonPath('data.notes', 'Updated notes')
            ->assertJsonPath('data.category', SavedPlaceCategory::Restaurant->value)
            ->assertJsonPath('data.is_favorite', true)
            ->assertJsonPath('data.version', 2);

        $this->deleteJson("/api/v1/saved-places/{$savedPlace->id}", [], $headers)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('saved_places', ['id' => $savedPlace->id]);
    }

    public function test_user_cannot_access_another_users_saved_place(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $headers = $this->authHeaders($intruder);

        $savedPlace = SavedPlace::factory()->for($owner)->create();

        $this->getJson("/api/v1/saved-places/{$savedPlace->id}", $headers)
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_map_pins_and_dashboard_only_return_owned_visible_places_in_bounds(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $headers = $this->authHeaders($user);

        $tokyo = Location::factory()->create([
            'name' => 'Shibuya Crossing',
            'city' => 'Tokyo',
            'country_code' => 'JP',
            'latitude' => 35.6595,
            'longitude' => 139.7005,
        ]);

        $outside = Location::factory()->create([
            'name' => 'Central Park',
            'city' => 'New York',
            'country_code' => 'US',
            'latitude' => 40.7829,
            'longitude' => -73.9654,
        ]);

        $hidden = Location::factory()->create([
            'name' => 'Hidden Place',
            'is_moderated_hidden' => true,
            'latitude' => 35.6,
            'longitude' => 139.7,
        ]);

        SavedPlace::factory()->for($user)->for($tokyo)->create([
            'category' => SavedPlaceCategory::Activity->value,
            'region_label' => 'Japan 2027',
            'is_favorite' => true,
        ]);
        SavedPlace::factory()->for($user)->for($outside)->create([
            'category' => SavedPlaceCategory::Restaurant->value,
            'region_label' => 'US Wishlist',
            'is_favorite' => false,
        ]);
        SavedPlace::factory()->for($user)->for($hidden)->create([
            'category' => SavedPlaceCategory::Hotel->value,
            'is_favorite' => false,
        ]);
        SavedPlace::factory()->for($otherUser)->for($tokyo)->create([
            'category' => SavedPlaceCategory::Activity->value,
        ]);

        $this->getJson('/api/v1/map/pins?north=36&south=35&east=140&west=139', $headers)
            ->assertOk()
            ->assertJsonPath('meta.count', 1)
            ->assertJsonPath('data.0.location_name', 'Shibuya Crossing');

        $this->getJson('/api/v1/dashboard', $headers)
            ->assertOk()
            ->assertJsonPath('data.summary.saved_places_count', 2)
            ->assertJsonPath('data.summary.favorite_places_count', 1)
            ->assertJsonPath('data.summary.mappable_places_count', 2)
            ->assertJsonPath('data.map_summary.total_pins', 2);
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
