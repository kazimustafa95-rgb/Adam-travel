<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\Trips\GenerateTripSuggestionsRequest;
use App\Http\Resources\Api\V1\TripAiRunResource;
use App\Http\Resources\Api\V1\TripPlaceResource;
use App\Http\Resources\Api\V1\TripSuggestionResource;
use App\Models\Trip;
use App\Models\TripSuggestion;
use App\Services\Trips\TripSuggestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TripSuggestionController extends BaseApiController
{
    public function __construct(protected TripSuggestionService $tripSuggestionService)
    {
    }

    public function index(Request $request, Trip $trip): JsonResponse
    {
        $this->authorize('viewSuggestions', $trip);
        $suggestions = $this->tripSuggestionService->list($trip);

        return $this->success(
            data: TripSuggestionResource::collection($suggestions)->resolve(),
            message: 'Trip suggestions loaded successfully.',
            meta: [
                'count' => $suggestions->count(),
            ],
        );
    }

    public function generate(GenerateTripSuggestionsRequest $request, Trip $trip): JsonResponse
    {
        $this->authorize('generateSuggestions', $trip);
        /** @var \App\Models\User $user */
        $user = $request->user();
        $payload = $this->tripSuggestionService->generate($trip, $user, $request->validated());

        return $this->success(
            data: TripSuggestionResource::collection($payload['suggestions'])->resolve(),
            message: 'Trip suggestions generated successfully.',
            meta: [
                'count' => $payload['suggestions']->count(),
                'trip_ai_run' => (new TripAiRunResource($payload['run']))->resolve(),
            ],
            status: $payload['run']->getAttribute('was_cached') ? 200 : 201,
        );
    }

    public function add(Request $request, Trip $trip, TripSuggestion $suggestion): JsonResponse
    {
        $this->authorize('manageSuggestions', $trip);
        /** @var \App\Models\User $user */
        $user = $request->user();
        $payload = $this->tripSuggestionService->accept($trip, $suggestion, $user);

        return $this->success(
            data: [
                'suggestion' => (new TripSuggestionResource($payload['suggestion']))->resolve(),
                'trip_place' => (new TripPlaceResource($payload['trip_place']))->resolve(),
            ],
            message: 'Trip suggestion added successfully.',
        );
    }

    public function dismiss(Request $request, Trip $trip, TripSuggestion $suggestion): JsonResponse
    {
        $this->authorize('manageSuggestions', $trip);
        $dismissed = $this->tripSuggestionService->dismiss($trip, $suggestion);

        return $this->success(
            data: (new TripSuggestionResource($dismissed))->resolve(),
            message: 'Trip suggestion dismissed successfully.',
        );
    }
}
