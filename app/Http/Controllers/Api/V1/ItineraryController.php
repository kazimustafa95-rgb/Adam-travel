<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\Trips\ReorderItineraryRequest;
use App\Http\Requests\Api\V1\Trips\StoreItineraryDayRequest;
use App\Http\Resources\Api\V1\ItineraryDayResource;
use App\Models\Trip;
use App\Services\Trips\ItineraryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItineraryController extends BaseApiController
{
    public function __construct(protected ItineraryService $itineraryService)
    {
    }

    public function index(Request $request, Trip $trip): JsonResponse
    {
        $this->authorize('view', $trip);
        $payload = $this->itineraryService->list($trip);

        return $this->success(
            data: ItineraryDayResource::collection($payload['days'])->resolve(),
            message: 'Itinerary loaded successfully.',
            meta: $payload['meta'],
        );
    }

    public function storeDay(StoreItineraryDayRequest $request, Trip $trip): JsonResponse
    {
        $this->authorize('manageItinerary', $trip);
        $day = $this->itineraryService->createDay($trip, $request->validated());

        return $this->success(
            data: (new ItineraryDayResource($day))->resolve(),
            message: 'Itinerary day created successfully.',
            meta: [
                'trip_version' => $trip->version,
            ],
            status: 201,
        );
    }

    public function reorder(ReorderItineraryRequest $request, Trip $trip): JsonResponse
    {
        $this->authorize('manageItinerary', $trip);
        $payload = $this->itineraryService->reorder($trip, $request->validated());

        return $this->success(
            data: ItineraryDayResource::collection($payload['days'])->resolve(),
            message: 'Itinerary reordered successfully.',
            meta: $payload['meta'],
        );
    }
}
