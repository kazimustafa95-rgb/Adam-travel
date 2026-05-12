<?php

namespace Tests\Feature\Api\V1;

use App\Enums\SavedPlaceCategory;
use App\Enums\TripMemberRole;
use App\Enums\TripPlaceSource;
use App\Models\Location;
use App\Models\SavedPlace;
use App\Models\Trip;
use App\Models\TripMember;
use App\Models\TripPlace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TripAiPlanningModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_generate_and_apply_ai_itinerary_proposal(): void
    {
        $owner = User::factory()->create();
        $trip = $this->ownedTrip($owner, [
            'start_date' => '2027-03-10',
            'end_date' => '2027-03-12',
        ]);

        $this->tripPlaceForTrip($trip, $owner, SavedPlaceCategory::Hotel, 'Tokyo Stay', 'Tokyo', 35.6762, 139.6503);
        $this->tripPlaceForTrip($trip, $owner, SavedPlaceCategory::Activity, 'TeamLab Planets', 'Tokyo', 35.6499, 139.7899);
        $this->tripPlaceForTrip($trip, $owner, SavedPlaceCategory::Restaurant, 'Sushi Dinner', 'Tokyo', 35.6895, 139.6917, true);
        $this->tripPlaceForTrip($trip, $owner, SavedPlaceCategory::Transport, 'Tokyo Station', 'Tokyo', 35.6812, 139.7671);

        $headers = $this->authHeaders($owner);

        $generateResponse = $this->postJson("/api/v1/trips/{$trip->id}/ai-itinerary/generate", [], $headers);
        $runId = $generateResponse->json('data.id');

        $generateResponse
            ->assertCreated()
            ->assertJsonPath('data.type', 'itinerary')
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.is_stale', false)
            ->assertJsonCount(3, 'data.result.days');

        $this->getJson("/api/v1/trips/{$trip->id}/ai-itinerary", $headers)
            ->assertOk()
            ->assertJsonPath('data.id', $runId);

        $this->postJson("/api/v1/trips/{$trip->id}/ai-itinerary/apply", [
            'trip_ai_run_id' => $runId,
        ], $headers)
            ->assertOk()
            ->assertJsonPath('meta.trip_ai_run.id', $runId)
            ->assertJsonPath('meta.items_count', 4)
            ->assertJsonPath('data.0.items.0.source', 'ai_generated');

        $this->assertDatabaseHas('itinerary_items', [
            'source' => 'ai_generated',
            'scheduled_by_user_id' => $owner->id,
        ]);
    }

    public function test_editor_can_view_but_cannot_generate_or_apply_ai_itinerary(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $trip = $this->ownedTrip($owner, [
            'start_date' => '2027-03-10',
            'end_date' => '2027-03-12',
        ]);

        TripMember::query()->create([
            'trip_id' => $trip->id,
            'user_id' => $editor->id,
            'role' => TripMemberRole::Editor,
            'joined_at' => now(),
        ]);

        $this->tripPlaceForTrip($trip, $owner, SavedPlaceCategory::Activity, 'Shibuya Crossing', 'Tokyo', 35.6595, 139.7005);

        $ownerHeaders = $this->authHeaders($owner);
        $editorHeaders = $this->authHeaders($editor);

        $this->postJson("/api/v1/trips/{$trip->id}/ai-itinerary/generate", [], $ownerHeaders)
            ->assertCreated();

        $this->getJson("/api/v1/trips/{$trip->id}/ai-itinerary", $editorHeaders)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->postJson("/api/v1/trips/{$trip->id}/ai-itinerary/generate", [], $editorHeaders)
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_editor_can_generate_suggestions_and_add_owner_saved_place_to_trip_pool(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $trip = $this->ownedTrip($owner, [
            'start_date' => '2027-03-10',
            'end_date' => '2027-03-12',
        ]);

        TripMember::query()->create([
            'trip_id' => $trip->id,
            'user_id' => $editor->id,
            'role' => TripMemberRole::Editor,
            'joined_at' => now(),
        ]);

        $this->tripPlaceForTrip($trip, $owner, SavedPlaceCategory::Activity, 'Asakusa Walk', 'Tokyo', 35.7148, 139.7967);

        $ownerRestaurant = $this->savedPlace($owner, SavedPlaceCategory::Restaurant, 'Tsukiji Lunch', 'Tokyo', 35.6655, 139.7708, true);
        $this->savedPlace($editor, SavedPlaceCategory::Hotel, 'Kyoto Stay', 'Kyoto', 35.0116, 135.7681);

        $headers = $this->authHeaders($editor);

        $generateResponse = $this->postJson("/api/v1/trips/{$trip->id}/suggestions/generate", [
            'limit' => 2,
        ], $headers);

        $suggestionId = collect($generateResponse->json('data'))
            ->firstWhere('saved_place_id', $ownerRestaurant->id)['id'] ?? null;

        $generateResponse
            ->assertCreated()
            ->assertJsonPath('meta.count', 2);

        $this->assertNotNull($suggestionId);

        $this->postJson("/api/v1/trips/{$trip->id}/suggestions/{$suggestionId}/add", [], $headers)
            ->assertOk()
            ->assertJsonPath('data.suggestion.status', 'accepted')
            ->assertJsonPath('data.trip_place.source', 'ai_suggestion')
            ->assertJsonPath('data.trip_place.saved_place_id', $ownerRestaurant->id);

        $remainingSuggestionId = collect($generateResponse->json('data'))
            ->first(fn (array $suggestion): bool => $suggestion['id'] !== $suggestionId)['id'] ?? null;

        $this->assertNotNull($remainingSuggestionId);

        $this->postJson("/api/v1/trips/{$trip->id}/suggestions/{$remainingSuggestionId}/dismiss", [], $headers)
            ->assertOk()
            ->assertJsonPath('data.status', 'dismissed');
    }

    public function test_balance_endpoint_returns_summary_and_category_gaps(): void
    {
        $owner = User::factory()->create();
        $trip = $this->ownedTrip($owner, [
            'start_date' => '2027-03-10',
            'end_date' => '2027-03-12',
        ]);

        $this->tripPlaceForTrip($trip, $owner, SavedPlaceCategory::Activity, 'Temple Visit', 'Kyoto', 35.0116, 135.7681);

        $this->getJson("/api/v1/trips/{$trip->id}/balance", $this->authHeaders($owner))
            ->assertOk()
            ->assertJsonPath('data.summary.total_pool_places', 1)
            ->assertJsonPath('data.summary.day_count', 3)
            ->assertJsonPath('data.categories.0.category', 'hotel')
            ->assertJsonFragment(['category' => 'restaurant'])
            ->assertJsonFragment(['category' => 'transport']);
    }

    public function test_applying_stale_ai_itinerary_is_rejected(): void
    {
        $owner = User::factory()->create();
        $trip = $this->ownedTrip($owner, [
            'start_date' => '2027-03-10',
            'end_date' => '2027-03-12',
        ]);

        $this->tripPlaceForTrip($trip, $owner, SavedPlaceCategory::Activity, 'Gion Walk', 'Kyoto', 35.0037, 135.7788);
        $headers = $this->authHeaders($owner);

        $runId = $this->postJson("/api/v1/trips/{$trip->id}/ai-itinerary/generate", [], $headers)
            ->assertCreated()
            ->json('data.id');

        $newSavedPlace = $this->savedPlace($owner, SavedPlaceCategory::Restaurant, 'Late-night Ramen', 'Kyoto', 35.0050, 135.7700);

        $this->postJson("/api/v1/trips/{$trip->id}/pool", [
            'saved_place_id' => $newSavedPlace->id,
        ], $headers)->assertCreated();

        $this->postJson("/api/v1/trips/{$trip->id}/ai-itinerary/apply", [
            'trip_ai_run_id' => $runId,
        ], $headers)
            ->assertStatus(409)
            ->assertJsonPath('success', false);
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
        SavedPlaceCategory $category,
        string $name,
        string $city,
        float $latitude,
        float $longitude,
        bool $isFavorite = false,
    ): SavedPlace {
        $location = Location::factory()->create([
            'name' => $name,
            'city' => $city,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'country_code' => 'JP',
        ]);

        return SavedPlace::factory()->for($owner)->for($location)->create([
            'category' => $category->value,
            'title_override' => $name,
            'is_favorite' => $isFavorite,
        ]);
    }

    protected function tripPlaceForTrip(
        Trip $trip,
        User $owner,
        SavedPlaceCategory $category,
        string $name,
        string $city,
        float $latitude,
        float $longitude,
        bool $isFavorite = false,
    ): TripPlace {
        $savedPlace = $this->savedPlace($owner, $category, $name, $city, $latitude, $longitude, $isFavorite);

        return TripPlace::query()->create([
            'trip_id' => $trip->id,
            'saved_place_id' => $savedPlace->id,
            'added_by_user_id' => $owner->id,
            'source' => TripPlaceSource::SavedPlace,
            'trip_category' => $savedPlace->category?->value,
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
