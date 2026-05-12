<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TripMemberRole;
use App\Http\Requests\Api\V1\Trips\UpdateTripMemberRequest;
use App\Http\Resources\Api\V1\TripMemberResource;
use App\Models\Trip;
use App\Models\TripMember;
use App\Services\Trips\TripPoolService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TripMemberController extends BaseApiController
{
    public function __construct(protected TripPoolService $tripPoolService)
    {
    }

    public function update(UpdateTripMemberRequest $request, Trip $trip, TripMember $member): JsonResponse
    {
        $this->authorize('manageMembers', $trip);

        if ($member->trip_id !== $trip->id) {
            throw ValidationException::withMessages([
                'member' => ['The selected member does not belong to this trip.'],
            ]);
        }

        if ($member->role === TripMemberRole::Owner) {
            throw ValidationException::withMessages([
                'member' => ['The trip owner role cannot be changed.'],
            ]);
        }

        $member->update([
            'role' => $request->validated('role'),
        ]);

        return $this->success(
            data: (new TripMemberResource($member->fresh()->load('user')))->resolve(),
            message: 'Trip member updated successfully.',
        );
    }

    public function destroy(Request $request, Trip $trip, TripMember $member): JsonResponse
    {
        $this->authorize('manageMembers', $trip);
        $this->tripPoolService->removeMember($trip, $member->id);

        return $this->success(
            message: 'Trip member removed successfully.',
        );
    }
}
