<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\Trips\StoreItineraryItemRequest;
use App\Http\Requests\Api\V1\Trips\UpdateItineraryItemRequest;
use App\Http\Resources\Api\V1\ItineraryItemResource;
use App\Models\ItineraryItem;
use App\Models\Trip;
use App\Services\Trips\ItineraryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ItineraryItemController extends BaseApiController
{
    public function __construct(protected ItineraryService $itineraryService)
    {
    }

    public function store(StoreItineraryItemRequest $request, Trip $trip): JsonResponse
    {
        $this->authorize('manageItinerary', $trip);
        /** @var \App\Models\User $user */
        $user = $request->user();
        $item = $this->itineraryService->createItem($trip, $user, $request->validated());

        return $this->success(
            data: (new ItineraryItemResource($item))->resolve(),
            message: 'Itinerary item created successfully.',
            meta: [
                'trip_version' => $trip->version,
            ],
            status: 201,
        );
    }

    public function update(UpdateItineraryItemRequest $request, Trip $trip, ItineraryItem $itineraryItem): JsonResponse
    {
        $this->guardItineraryItemTrip($trip, $itineraryItem);
        $this->authorize('update', $itineraryItem);
        $item = $this->itineraryService->updateItem($trip, $itineraryItem, $request->validated());

        return $this->success(
            data: (new ItineraryItemResource($item))->resolve(),
            message: 'Itinerary item updated successfully.',
            meta: [
                'trip_version' => $trip->version,
            ],
        );
    }

    public function destroy(Request $request, Trip $trip, ItineraryItem $itineraryItem): JsonResponse
    {
        $this->guardItineraryItemTrip($trip, $itineraryItem);
        $this->authorize('delete', $itineraryItem);
        $this->itineraryService->deleteItem($trip, $itineraryItem);

        return $this->success(
            message: 'Itinerary item removed successfully.',
            meta: [
                'trip_version' => $trip->version,
            ],
        );
    }

    protected function guardItineraryItemTrip(Trip $trip, ItineraryItem $itineraryItem): void
    {
        if ($itineraryItem->day->trip_id !== $trip->id) {
            throw ValidationException::withMessages([
                'itinerary_item' => ['The selected itinerary item does not belong to this trip.'],
            ]);
        }
    }
}
