<?php

namespace Tests\Feature\Api\V1;

use App\Enums\TripMemberRole;
use App\Enums\TripStatus;
use App\Models\SavedPlace;
use App\Models\Trip;
use App\Models\TripInvite;
use App\Models\TripMember;
use App\Models\TripPlace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TripsModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_and_view_a_trip_with_owner_membership(): void
    {
        $user = User::factory()->create();
        $headers = $this->authHeaders($user);

        $response = $this->postJson('/api/v1/trips', [
            'title' => 'Japan Spring 2027',
            'description' => 'A collaborative spring route through Tokyo and Kyoto.',
            'start_location_name' => 'Tokyo',
            'end_location_name' => 'Kyoto',
            'start_date' => '2027-03-10',
            'end_date' => '2027-03-18',
            'status' => TripStatus::Draft->value,
        ], $headers);

        $response
            ->assertCreated()
            ->assertJsonPath('data.title', 'Japan Spring 2027')
            ->assertJsonPath('data.current_user_role', TripMemberRole::Owner->value)
            ->assertJsonPath('data.member_count', 1);

        $tripId = $response->json('data.id');

        $this->assertDatabaseHas('trips', [
            'id' => $tripId,
            'owner_user_id' => $user->id,
            'status' => TripStatus::Draft->value,
        ]);

        $this->assertDatabaseHas('trip_members', [
            'trip_id' => $tripId,
            'user_id' => $user->id,
            'role' => TripMemberRole::Owner->value,
        ]);

        $this->getJson("/api/v1/trips/{$tripId}", $headers)
            ->assertOk()
            ->assertJsonPath('data.members.0.user.email', $user->email);
    }

    public function test_owner_can_invite_editor_and_user_can_accept_invite(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create(['email' => 'friend@example.com']);
        $trip = $this->ownedTrip($owner);

        $ownerHeaders = $this->authHeaders($owner);
        $inviteeHeaders = $this->authHeaders($invitee);

        $inviteResponse = $this->postJson("/api/v1/trips/{$trip->id}/invites", [
            'email' => 'friend@example.com',
            'role' => TripMemberRole::Editor->value,
        ], $ownerHeaders);

        $token = $inviteResponse->json('data.token');

        $inviteResponse
            ->assertCreated()
            ->assertJsonPath('data.role', TripMemberRole::Editor->value)
            ->assertJsonPath('data.status', 'pending');

        $this->postJson("/api/v1/trip-invites/{$token}/accept", [], $inviteeHeaders)
            ->assertOk()
            ->assertJsonPath('data.current_user_role', TripMemberRole::Editor->value);

        $this->assertDatabaseHas('trip_members', [
            'trip_id' => $trip->id,
            'user_id' => $invitee->id,
            'role' => TripMemberRole::Editor->value,
        ]);
        $this->assertDatabaseHas('trip_invites', [
            'trip_id' => $trip->id,
            'email' => 'friend@example.com',
            'status' => 'accepted',
        ]);
    }

    public function test_owner_can_update_member_role_and_revoke_member(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $trip = $this->ownedTrip($owner);
        $member = TripMember::query()->create([
            'trip_id' => $trip->id,
            'user_id' => $editor->id,
            'role' => TripMemberRole::Editor,
            'joined_at' => now(),
        ]);

        $headers = $this->authHeaders($owner);

        $this->patchJson("/api/v1/trips/{$trip->id}/members/{$member->id}", [
            'role' => TripMemberRole::Viewer->value,
        ], $headers)
            ->assertOk()
            ->assertJsonPath('data.role', TripMemberRole::Viewer->value);

        $this->deleteJson("/api/v1/trips/{$trip->id}/members/{$member->id}", [], $headers)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('trip_members', [
            'id' => $member->id,
        ]);
    }

    public function test_owner_and_editor_can_manage_trip_pool_and_hearts_but_viewer_cannot(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $viewer = User::factory()->create();
        $trip = $this->ownedTrip($owner);

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

        $editorSavedPlace = SavedPlace::factory()->for($editor)->create();
        $viewerSavedPlace = SavedPlace::factory()->for($viewer)->create();

        $editorHeaders = $this->authHeaders($editor);
        $viewerHeaders = $this->authHeaders($viewer);
        $ownerHeaders = $this->authHeaders($owner);

        $addResponse = $this->postJson("/api/v1/trips/{$trip->id}/pool", [
            'saved_place_id' => $editorSavedPlace->id,
            'notes' => 'Great sunset option',
        ], $editorHeaders);

        $tripPlaceId = $addResponse->json('data.id');

        $addResponse
            ->assertCreated()
            ->assertJsonPath('data.saved_place_id', $editorSavedPlace->id)
            ->assertJsonPath('data.hearts_count', 0);

        $this->postJson("/api/v1/trips/{$trip->id}/pool/{$tripPlaceId}/heart", [], $ownerHeaders)
            ->assertOk()
            ->assertJsonPath('data.hearts_count', 1);

        $this->postJson("/api/v1/trips/{$trip->id}/pool/{$tripPlaceId}/heart", [], $viewerHeaders)
            ->assertStatus(403)
            ->assertJsonPath('success', false);

        $this->postJson("/api/v1/trips/{$trip->id}/pool", [
            'saved_place_id' => $viewerSavedPlace->id,
        ], $editorHeaders)
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->deleteJson("/api/v1/trips/{$trip->id}/pool/{$tripPlaceId}", [], $ownerHeaders)
            ->assertOk();

        $this->assertSoftDeleted('trip_places', [
            'id' => $tripPlaceId,
        ]);
    }

    public function test_non_member_cannot_view_trip(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $trip = $this->ownedTrip($owner);

        $this->getJson("/api/v1/trips/{$trip->id}", $this->authHeaders($outsider))
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_trip_index_lists_only_memberships(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->create();

        $trip = $this->ownedTrip($owner, [
            'title' => 'Europe Summer',
        ]);
        TripMember::query()->create([
            'trip_id' => $trip->id,
            'user_id' => $member->id,
            'role' => TripMemberRole::Viewer,
            'joined_at' => now(),
        ]);
        $otherTrip = $this->ownedTrip($outsider, [
            'title' => 'Private Trip',
        ]);

        $this->getJson('/api/v1/trips?q=Europe', $this->authHeaders($member))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Europe Summer');

        $this->getJson('/api/v1/trips?q=Private', $this->authHeaders($member))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
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
