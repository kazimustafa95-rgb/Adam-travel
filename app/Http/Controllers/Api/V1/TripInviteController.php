<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\Trips\StoreTripInviteRequest;
use App\Http\Resources\Api\V1\TripInviteResource;
use App\Http\Resources\Api\V1\TripResource;
use App\Models\Trip;
use App\Models\TripInvite;
use App\Services\Trips\TripInviteService;
use App\Services\Trips\TripService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TripInviteController extends BaseApiController
{
    public function __construct(
        protected TripInviteService $tripInviteService,
        protected TripService $tripService,
    ) {
    }

    public function store(StoreTripInviteRequest $request, Trip $trip): JsonResponse
    {
        $this->authorize('manageInvites', $trip);
        /** @var \App\Models\User $user */
        $user = $request->user();
        $invite = $this->tripInviteService->create($trip, $user, $request->validated());

        return $this->success(
            data: (new TripInviteResource($invite))->resolve(),
            message: 'Trip invite created successfully.',
            status: 201,
        );
    }

    public function accept(Request $request, string $token): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $trip = $this->tripInviteService->accept($token, $user);

        return $this->success(
            data: (new TripResource($this->tripService->detailForUser($trip, $user)))->resolve(),
            message: 'Trip invite accepted successfully.',
        );
    }

    public function destroy(Request $request, Trip $trip, TripInvite $invite): JsonResponse
    {
        $this->authorize('manageInvites', $trip);
        $revokedInvite = $this->tripInviteService->revoke($trip, $invite);

        return $this->success(
            data: (new TripInviteResource($revokedInvite))->resolve(),
            message: 'Trip invite revoked successfully.',
        );
    }
}
