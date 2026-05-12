<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\Trips\StoreTripRequest;
use App\Http\Requests\Api\V1\Trips\TripIndexRequest;
use App\Http\Requests\Api\V1\Trips\UpdateTripRequest;
use App\Http\Resources\Api\V1\TripResource;
use App\Models\Trip;
use App\Services\Trips\TripService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TripController extends BaseApiController
{
    public function __construct(protected TripService $tripService)
    {
    }

    public function index(TripIndexRequest $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $paginator = $this->tripService->paginateForUser($user, $request->validated());

        return $this->success(
            data: TripResource::collection($paginator->items())->resolve(),
            message: 'Trips loaded successfully.',
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function store(StoreTripRequest $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $trip = $this->tripService->create($user, $request->validated());

        return $this->success(
            data: (new TripResource($trip))->resolve(),
            message: 'Trip created successfully.',
            status: 201,
        );
    }

    public function show(Request $request, Trip $trip): JsonResponse
    {
        $this->authorize('view', $trip);
        /** @var \App\Models\User $user */
        $user = $request->user();

        return $this->success(
            data: (new TripResource($this->tripService->detailForUser($trip, $user)))->resolve(),
            message: 'Trip loaded successfully.',
        );
    }

    public function update(UpdateTripRequest $request, Trip $trip): JsonResponse
    {
        $this->authorize('update', $trip);
        /** @var \App\Models\User $user */
        $user = $request->user();
        $updatedTrip = $this->tripService->update($trip, $request->validated(), $user);

        return $this->success(
            data: (new TripResource($updatedTrip))->resolve(),
            message: 'Trip updated successfully.',
        );
    }

    public function destroy(Request $request, Trip $trip): JsonResponse
    {
        $this->authorize('delete', $trip);
        $this->tripService->delete($trip);

        return $this->success(
            message: 'Trip deleted successfully.',
        );
    }
}
