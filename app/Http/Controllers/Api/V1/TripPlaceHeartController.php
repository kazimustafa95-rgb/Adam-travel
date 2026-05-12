<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\TripPlaceResource;
use App\Models\Trip;
use App\Models\TripPlace;
use App\Services\Trips\TripPoolService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TripPlaceHeartController extends BaseApiController
{
    public function __construct(protected TripPoolService $tripPoolService)
    {
    }

    public function store(Request $request, Trip $trip, TripPlace $tripPlace): JsonResponse
    {
        $this->guardTripPlaceTrip($trip, $tripPlace);
        $this->authorize('heart', $tripPlace);
        /** @var \App\Models\User $user */
        $user = $request->user();
        $updatedTripPlace = $this->tripPoolService->heart($tripPlace, $user);

        return $this->success(
            data: (new TripPlaceResource($updatedTripPlace))->resolve(),
            message: 'Trip place heart added successfully.',
        );
    }

    public function destroy(Request $request, Trip $trip, TripPlace $tripPlace): JsonResponse
    {
        $this->guardTripPlaceTrip($trip, $tripPlace);
        $this->authorize('heart', $tripPlace);
        /** @var \App\Models\User $user */
        $user = $request->user();
        $updatedTripPlace = $this->tripPoolService->unheart($tripPlace, $user);

        return $this->success(
            data: (new TripPlaceResource($updatedTripPlace))->resolve(),
            message: 'Trip place heart removed successfully.',
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
