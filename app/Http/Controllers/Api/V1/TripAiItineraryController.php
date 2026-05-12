<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\Trips\ApplyTripAiItineraryRequest;
use App\Http\Requests\Api\V1\Trips\GenerateTripAiItineraryRequest;
use App\Http\Resources\Api\V1\ItineraryDayResource;
use App\Http\Resources\Api\V1\TripAiRunResource;
use App\Models\Trip;
use App\Services\Trips\TripAiItineraryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TripAiItineraryController extends BaseApiController
{
    public function __construct(protected TripAiItineraryService $tripAiItineraryService)
    {
    }

    public function show(Request $request, Trip $trip): JsonResponse
    {
        $this->authorize('viewAiItinerary', $trip);
        $run = $this->tripAiItineraryService->latestForTrip($trip);

        return $this->success(
            data: $run ? (new TripAiRunResource($run))->resolve() : null,
            message: 'AI itinerary loaded successfully.',
        );
    }

    public function generate(GenerateTripAiItineraryRequest $request, Trip $trip): JsonResponse
    {
        $this->authorize('generateAiItinerary', $trip);
        /** @var \App\Models\User $user */
        $user = $request->user();
        $run = $this->tripAiItineraryService->generate($trip, $user, $request->validated());

        return $this->success(
            data: (new TripAiRunResource($run))->resolve(),
            message: 'AI itinerary generated successfully.',
            status: $run->getAttribute('was_cached') ? 200 : 201,
        );
    }

    public function apply(ApplyTripAiItineraryRequest $request, Trip $trip): JsonResponse
    {
        $this->authorize('applyAiItinerary', $trip);
        /** @var \App\Models\User $user */
        $user = $request->user();
        $payload = $this->tripAiItineraryService->apply($trip, $user, $request->validated('trip_ai_run_id'));

        return $this->success(
            data: ItineraryDayResource::collection($payload['itinerary']['days'])->resolve(),
            message: 'AI itinerary applied successfully.',
            meta: array_merge($payload['itinerary']['meta'], [
                'trip_ai_run' => (new TripAiRunResource($payload['run']))->resolve(),
            ]),
        );
    }
}
