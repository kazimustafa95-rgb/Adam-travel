<?php

namespace Tests\Feature\Api\V1;

use App\Enums\ImportStatus;
use App\Enums\SavedPlaceCategory;
use App\Models\Import;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportsModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_submit_text_import_and_receive_candidate_ready_for_confirmation(): void
    {
        $user = User::factory()->create();
        $headers = $this->authHeaders($user);

        $response = $this->postJson('/api/v1/imports', [
            'raw_text' => 'Place: Senso-ji Temple. City: Tokyo. Country: JP. Category: activity. Coordinates: 35.7148, 139.7967. Historic temple district with a lively market street.',
        ], $headers);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', ImportStatus::AwaitingConfirmation->value)
            ->assertJsonPath('data.candidates.0.place_name', 'Senso-ji Temple')
            ->assertJsonPath('data.candidates.0.city', 'Tokyo')
            ->assertJsonPath('data.candidates.0.latitude', 35.7148);

        $this->assertDatabaseHas('imports', [
            'user_id' => $user->id,
            'status' => ImportStatus::AwaitingConfirmation->value,
        ]);
        $this->assertDatabaseHas('import_candidates', [
            'place_name' => 'Senso-ji Temple',
            'city' => 'Tokyo',
        ]);
    }

    public function test_user_can_confirm_import_into_a_saved_place(): void
    {
        $user = User::factory()->create();
        $headers = $this->authHeaders($user);

        $importResponse = $this->postJson('/api/v1/imports', [
            'raw_text' => 'Place: Shinjuku Gyoen. City: Tokyo. Country: JP. Category: activity. Coordinates: 35.6852, 139.7100. Peaceful gardens and seasonal blooms.',
        ], $headers);

        $importId = $importResponse->json('data.id');
        $candidateId = $importResponse->json('data.candidates.0.id');

        $confirmResponse = $this->postJson("/api/v1/imports/{$importId}/confirm", [
            'candidate_id' => $candidateId,
            'category' => SavedPlaceCategory::Activity->value,
            'region_label' => 'Japan 2027',
            'is_favorite' => true,
        ], $headers);

        $confirmResponse
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.import.status', ImportStatus::Completed->value)
            ->assertJsonPath('data.saved_place.location.name', 'Shinjuku Gyoen')
            ->assertJsonPath('data.saved_place.region_label', 'Japan 2027')
            ->assertJsonPath('data.saved_place.is_favorite', true);

        $this->assertDatabaseHas('saved_places', [
            'user_id' => $user->id,
            'import_id' => $importId,
            'region_label' => 'Japan 2027',
        ]);
    }

    public function test_import_without_coordinates_enters_manual_review_and_can_be_manually_corrected(): void
    {
        $user = User::factory()->create();
        $headers = $this->authHeaders($user);

        $importResponse = $this->postJson('/api/v1/imports', [
            'raw_text' => 'Place: Central Park. City: New York. Country: US. Category: activity. Famous park for long walks and city views.',
        ], $headers);

        $importId = $importResponse->json('data.id');

        $importResponse
            ->assertCreated()
            ->assertJsonPath('data.status', ImportStatus::ManualReview->value)
            ->assertJsonPath('data.requires_manual_review', true);

        $overrideResponse = $this->patchJson("/api/v1/imports/{$importId}/manual-override", [
            'place_name' => 'Central Park',
            'category' => SavedPlaceCategory::Activity->value,
            'city' => 'New York',
            'country' => 'US',
            'latitude' => 40.7829,
            'longitude' => -73.9654,
            'summary' => 'Manual correction added exact park coordinates.',
        ], $headers);

        $overrideResponse
            ->assertOk()
            ->assertJsonPath('data.status', ImportStatus::AwaitingConfirmation->value)
            ->assertJsonPath('data.candidates.0.latitude', 40.7829)
            ->assertJsonPath('data.candidates.0.summary', 'Manual correction added exact park coordinates.');

        $this->postJson("/api/v1/imports/{$importId}/confirm", [
            'category' => SavedPlaceCategory::Activity->value,
        ], $headers)
            ->assertOk()
            ->assertJsonPath('data.import.status', ImportStatus::Completed->value)
            ->assertJsonPath('data.saved_place.location.latitude', 40.7829);
    }

    public function test_failed_import_can_be_retried(): void
    {
        $user = User::factory()->create();
        $headers = $this->authHeaders($user);

        $response = $this->postJson('/api/v1/imports', [
            'raw_text' => 'nothing useful here',
        ], $headers);

        $importId = $response->json('data.id');

        $response
            ->assertCreated()
            ->assertJsonPath('data.status', ImportStatus::Failed->value)
            ->assertJsonPath('data.error_code', 'no_location_detected');

        $this->postJson("/api/v1/imports/{$importId}/retry", [], $headers)
            ->assertOk()
            ->assertJsonPath('data.status', ImportStatus::Failed->value)
            ->assertJsonPath('data.error_code', 'no_location_detected');
    }

    public function test_user_cannot_view_another_users_import(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $headers = $this->authHeaders($intruder);

        $import = Import::factory()->for($owner)->create();

        $this->getJson("/api/v1/imports/{$import->id}", $headers)
            ->assertStatus(403)
            ->assertJsonPath('success', false);
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
