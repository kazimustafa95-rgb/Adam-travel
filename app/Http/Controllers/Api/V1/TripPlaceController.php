<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\Trips\StoreTripPlaceRequest;
use App\Http\Requests\Api\V1\Trips\UpdateTripPlaceRequest;
use App\Http\Resources\Api\V1\TripPlaceResource;
use App\Models\Trip;
use App\Models\TripPlace;
use App\Services\Trips\TripPoolService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TripPlaceController extends BaseApiController
{
    public function __construct(protected TripPoolService $tripPoolService)
    {
    }

    public function index(Request $request, Trip $trip): JsonResponse
    {
        $this->authorize('view', $trip);
        $pool = $this->tripPoolService->list($trip);

        return $this->success(
            data: TripPlaceResource::collection($pool)->resolve(),
            message: 'Trip pool loaded successfully.',
            meta: [
                'count' => $pool->count(),
            ],
        );
    }

    public function store(StoreTripPlaceRequest $request, Trip $trip): JsonResponse
    {
        $this->authorize('contribute', $trip);
        /** @var \App\Models\User $user */
        $user = $request->user();
        $tripPlace = $this->tripPoolService->create($trip, $user, $request->validated());

        return $this->success(
            data: (new TripPlaceResource($tripPlace))->resolve(),
            message: 'Trip place added successfully.',
            status: 201,
        );
    }

    public function update(UpdateTripPlaceRequest $request, Trip $trip, TripPlace $tripPlace): JsonResponse
    {
        $this->guardTripPlaceTrip($trip, $tripPlace);
        $this->authorize('update', $tripPlace);
        $updatedTripPlace = $this->tripPoolService->update($tripPlace, $request->validated());

        return $this->success(
            data: (new TripPlaceResource($updatedTripPlace))->resolve(),
            message: 'Trip place updated successfully.',
        );
    }

    public function destroy(Request $request, Trip $trip, TripPlace $tripPlace): JsonResponse
    {
        $this->guardTripPlaceTrip($trip, $tripPlace);
        $this->authorize('delete', $tripPlace);
        $this->tripPoolService->delete($tripPlace);

        return $this->success(
            message: 'Trip place removed successfully.',
        );
    }

    protected function guardTripPlaceTrip(Trip $trip, TripPlace $tripPlace): void
    {
        if ($tripPlace->trip_id !== $trip->id) {
            throw ValidationException::withMessages([
                'trip_place' => ['The selected trip place does not belong to this trip.'],
            ]);
        }
    }
}
