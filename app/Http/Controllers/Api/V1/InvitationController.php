<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\FriendRequestResource;
use App\Http\Resources\Api\V1\TripInvitationResource;
use App\Http\Resources\Api\V1\TripResource;
use App\Models\FriendRequest;
use App\Models\TripInvite;
use App\Services\Trips\TripInviteService;
use App\Services\Trips\TripService;
use App\Services\Users\FriendConnectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InvitationController extends BaseApiController
{
    public function __construct(
        protected TripInviteService $tripInviteService,
        protected FriendConnectionService $friendConnectionService,
        protected TripService $tripService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tab' => ['sometimes', Rule::in(['trips', 'friends', 'all'])],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();
        $tab = $validated['tab'] ?? 'trips';
        $tripInvites = $this->tripInviteService->pendingInboxForUser($user);
        $friendRequests = $this->friendConnectionService->incomingRequests($user);

        return $this->success(
            data: [
                'selected_tab' => $tab,
                'counts' => [
                    'trips' => $tripInvites->count(),
                    'friends' => $friendRequests->count(),
                    'total' => $tripInvites->count() + $friendRequests->count(),
                ],
                'trip_invitations' => in_array($tab, ['trips', 'all'], true)
                    ? TripInvitationResource::collection($tripInvites)->resolve()
                    : [],
                'friend_requests' => in_array($tab, ['friends', 'all'], true)
                    ? FriendRequestResource::collection($friendRequests)->resolve()
                    : [],
            ],
            message: 'Invitations loaded successfully.',
        );
    }

    public function acceptTrip(Request $request, TripInvite $invite): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $trip = $this->tripInviteService->acceptInvite($invite, $user);

        return $this->success(
            data: (new TripResource($this->tripService->detailForUser($trip, $user)))->resolve(),
            message: 'Trip invitation accepted successfully.',
        );
    }

    public function declineTrip(Request $request, TripInvite $invite): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $declinedInvite = $this->tripInviteService->decline($invite, $user);

        return $this->success(
            data: (new TripInvitationResource($declinedInvite))->resolve(),
            message: 'Trip invitation declined successfully.',
        );
    }

    public function acceptFriend(Request $request, FriendRequest $friendRequest): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $acceptedRequest = $this->friendConnectionService->accept($friendRequest, $user);

        return $this->success(
            data: (new FriendRequestResource($acceptedRequest))->resolve(),
            message: 'Friend request accepted successfully.',
        );
    }

    public function declineFriend(Request $request, FriendRequest $friendRequest): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $declinedRequest = $this->friendConnectionService->decline($friendRequest, $user);

        return $this->success(
            data: (new FriendRequestResource($declinedRequest))->resolve(),
            message: 'Friend request declined successfully.',
        );
    }

    public function acceptAllFriends(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $acceptedCount = $this->friendConnectionService->acceptAll($user);

        return $this->success(
            data: [
                'accepted_count' => $acceptedCount,
                'friend_requests' => FriendRequestResource::collection($this->friendConnectionService->incomingRequests($user))->resolve(),
            ],
            message: 'All pending friend requests were accepted successfully.',
        );
    }
}
