<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\SavedPlaces\StoreSavedPlaceTripLinkRequest;
use App\Http\Resources\Api\V1\TripPlaceResource;
use App\Models\SavedPlace;
use App\Models\Trip;
use App\Models\User;
use App\Services\Trips\TripPoolService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SavedPlaceTripLinkController extends BaseApiController
{
    public function __construct(protected TripPoolService $tripPoolService) {}

    public function index(Request $request, SavedPlace $savedPlace): JsonResponse
    {
        $this->authorize('view', $savedPlace);
        /** @var User $user */
        $user = $request->user();

        return $this->success(
            data: $this->tripPoolService->tripOptionsForUser($user, $savedPlace),
            message: 'Trip options loaded successfully.',
        );
    }

    public function store(StoreSavedPlaceTripLinkRequest $request, SavedPlace $savedPlace): JsonResponse
    {
        $this->authorize('update', $savedPlace);
        /** @var User $user */
        $user = $request->user();
        $trip = Trip::query()->findOrFail($request->integer('trip_id'));
        $this->authorize('contribute', $trip);

        $tripPlace = $this->tripPoolService->createFromOwnedSavedPlace(
            $trip,
            $savedPlace,
            $user,
            $request->validated(),
        );

        return $this->success(
            data: (new TripPlaceResource($tripPlace))->resolve(),
            message: 'Saved place added to trip successfully.',
            status: 201,
        );
    }
}
