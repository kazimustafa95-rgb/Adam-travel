<?php

namespace Tests\Feature\Api\V1;

use App\Enums\TripMemberRole;
use App\Enums\TripPlaceSource;
use App\Models\ItineraryDay;
use App\Models\ItineraryItem;
use App\Models\SavedPlace;
use App\Models\Trip;
use App\Models\TripMember;
use App\Models\TripPlace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TripItineraryModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_itinerary_days_and_schedule_trip_pool_items(): void
    {
        $owner = User::factory()->create();
        $trip = $this->ownedTrip($owner, [
            'start_date' => '2027-03-10',
            'end_date' => '2027-03-12',
        ]);
        $tripPlace = $this->tripPlaceForTrip($trip, $owner);
        $headers = $this->authHeaders($owner);

        $dayResponse = $this->postJson("/api/v1/trips/{$trip->id}/itinerary/days", [
            'day_number' => 1,
            'title' => 'Arrival Day',
        ], $headers);

        $dayId = $dayResponse->json('data.id');

        $dayResponse
            ->assertCreated()
            ->assertJsonPath('data.day_number', 1)
            ->assertJsonPath('data.trip_date', '2027-03-10')
            ->assertJsonPath('data.title', 'Arrival Day')
            ->assertJsonPath('meta.trip_version', 2);

        $itemResponse = $this->postJson("/api/v1/trips/{$trip->id}/itinerary/items", [
            'itinerary_day_id' => $dayId,
            'trip_place_id' => $tripPlace->id,
            'starts_at' => '2027-03-10T10:00:00Z',
            'notes' => 'Check in and explore nearby.',
        ], $headers);

        $itemResponse
            ->assertCreated()
            ->assertJsonPath('data.itinerary_day_id', $dayId)
            ->assertJsonPath('data.trip_place_id', $tripPlace->id)
            ->assertJsonPath('data.notes', 'Check in and explore nearby.')
            ->assertJsonPath('meta.trip_version', 3);

        $this->assertDatabaseHas('itinerary_items', [
            'trip_place_id' => $tripPlace->id,
            'scheduled_by_user_id' => $owner->id,
        ]);

        $this->getJson("/api/v1/trips/{$trip->id}/itinerary", $headers)
            ->assertOk()
            ->assertJsonPath('meta.count', 1)
            ->assertJsonPath('meta.items_count', 1)
            ->assertJsonPath('meta.unscheduled_pool_count', 0)
            ->assertJsonPath('data.0.items.0.trip_place.id', $tripPlace->id);
    }

    public function test_trip_members_can_view_itinerary_but_only_owner_can_mutate_it(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $viewer = User::factory()->create();
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
        TripMember::query()->create([
            'trip_id' => $trip->id,
            'user_id' => $viewer->id,
            'role' => TripMemberRole::Viewer,
            'joined_at' => now(),
        ]);

        $ownerHeaders = $this->authHeaders($owner);
        $editorHeaders = $this->authHeaders($editor);
        $viewerHeaders = $this->authHeaders($viewer);

        $this->postJson("/api/v1/trips/{$trip->id}/itinerary/days", [
            'day_number' => 1,
        ], $ownerHeaders)->assertCreated();

        $this->getJson("/api/v1/trips/{$trip->id}/itinerary", $viewerHeaders)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->postJson("/api/v1/trips/{$trip->id}/itinerary/days", [
            'day_number' => 2,
        ], $editorHeaders)
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_owner_can_reorder_itinerary_items_across_days_with_version_checks(): void
    {
        $owner = User::factory()->create();
        $trip = $this->ownedTrip($owner, [
            'start_date' => '2027-03-10',
            'end_date' => '2027-03-12',
            'version' => 1,
        ]);

        $dayOne = ItineraryDay::query()->create([
            'trip_id' => $trip->id,
            'day_number' => 1,
            'trip_date' => '2027-03-10',
            'version' => 1,
        ]);
        $dayTwo = ItineraryDay::query()->create([
            'trip_id' => $trip->id,
            'day_number' => 2,
            'trip_date' => '2027-03-11',
            'version' => 1,
        ]);

        $firstTripPlace = $this->tripPlaceForTrip($trip, $owner);
        $secondTripPlace = $this->tripPlaceForTrip($trip, $owner);

        $firstItem = ItineraryItem::query()->create([
            'itinerary_day_id' => $dayOne->id,
            'trip_place_id' => $firstTripPlace->id,
            'scheduled_by_user_id' => $owner->id,
            'source' => 'manual',
            'sort_order' => 1,
            'version' => 1,
        ]);
        $secondItem = ItineraryItem::query()->create([
            'itinerary_day_id' => $dayOne->id,
            'trip_place_id' => $secondTripPlace->id,
            'scheduled_by_user_id' => $owner->id,
            'source' => 'manual',
            'sort_order' => 2,
            'version' => 1,
        ]);

        $headers = $this->authHeaders($owner);

        $this->putJson("/api/v1/trips/{$trip->id}/itinerary/reorder", [
            'version' => 1,
            'days' => [
                [
                    'day_id' => $dayOne->id,
                    'items' => [
                        [
                            'item_id' => $secondItem->id,
                            'sort_order' => 1,
                            'starts_at' => '2027-03-10T12:00:00Z',
                        ],
                    ],
                ],
                [
                    'day_id' => $dayTwo->id,
                    'items' => [
                        [
                            'item_id' => $firstItem->id,
                            'sort_order' => 1,
                            'starts_at' => '2027-03-11T09:00:00Z',
                        ],
                    ],
                ],
            ],
        ], $headers)
            ->assertOk()
            ->assertJsonPath('meta.trip_version', 2)
            ->assertJsonPath('data.0.items.0.id', $secondItem->id)
            ->assertJsonPath('data.1.items.0.id', $firstItem->id);

        $this->assertDatabaseHas('itinerary_items', [
            'id' => $firstItem->id,
            'itinerary_day_id' => $dayTwo->id,
            'sort_order' => 1,
        ]);
        $this->assertDatabaseHas('itinerary_items', [
            'id' => $secondItem->id,
            'itinerary_day_id' => $dayOne->id,
            'sort_order' => 1,
        ]);

        $this->putJson("/api/v1/trips/{$trip->id}/itinerary/reorder", [
            'version' => 1,
            'days' => [
                [
                    'day_id' => $dayOne->id,
                    'items' => [
                        [
                            'item_id' => $secondItem->id,
                            'sort_order' => 1,
                        ],
                    ],
                ],
            ],
        ], $headers)
            ->assertStatus(409)
            ->assertJsonPath('success', false);
    }

    public function test_itinerary_rejects_duplicate_and_foreign_trip_places(): void
    {
        $owner = User::factory()->create();
        $trip = $this->ownedTrip($owner, [
            'start_date' => '2027-03-10',
            'end_date' => '2027-03-12',
        ]);
        $otherTrip = $this->ownedTrip($owner, [
            'title' => 'Other Trip',
            'start_date' => '2027-04-01',
            'end_date' => '2027-04-03',
        ]);
        $day = ItineraryDay::query()->create([
            'trip_id' => $trip->id,
            'day_number' => 1,
            'trip_date' => '2027-03-10',
            'version' => 1,
        ]);
        $tripPlace = $this->tripPlaceForTrip($trip, $owner);
        $foreignTripPlace = $this->tripPlaceForTrip($otherTrip, $owner);
        $headers = $this->authHeaders($owner);

        $this->postJson("/api/v1/trips/{$trip->id}/itinerary/items", [
            'itinerary_day_id' => $day->id,
            'trip_place_id' => $tripPlace->id,
        ], $headers)->assertCreated();

        $this->postJson("/api/v1/trips/{$trip->id}/itinerary/items", [
            'itinerary_day_id' => $day->id,
            'trip_place_id' => $tripPlace->id,
        ], $headers)
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.trip_place_id.0', 'This trip place is already scheduled in the itinerary.');

        $this->postJson("/api/v1/trips/{$trip->id}/itinerary/items", [
            'itinerary_day_id' => $day->id,
            'trip_place_id' => $foreignTripPlace->id,
        ], $headers)
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.trip_place_id.0', 'The selected trip place does not belong to this trip.');
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

    protected function tripPlaceForTrip(Trip $trip, User $user): TripPlace
    {
        $savedPlace = SavedPlace::factory()->for($user)->create();

        return TripPlace::query()->create([
            'trip_id' => $trip->id,
            'saved_place_id' => $savedPlace->id,
            'added_by_user_id' => $user->id,
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
