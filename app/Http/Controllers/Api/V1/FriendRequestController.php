<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\Profile\StoreFriendRequestRequest;
use App\Http\Resources\Api\V1\FriendRequestResource;
use App\Http\Resources\Api\V1\FriendResource;
use App\Models\FriendRequest;
use App\Services\Users\FriendConnectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FriendRequestController extends BaseApiController
{
    public function __construct(protected FriendConnectionService $friendConnectionService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        return $this->success(
            data: [
                'friends' => FriendResource::collection($this->friendConnectionService->friendships($user))->resolve(),
                'counts' => [
                    'friends' => $this->friendConnectionService->friendsCountForUser($user),
                    'pending_requests' => $this->friendConnectionService->pendingCountForUser($user),
                ],
            ],
            message: 'Friends loaded successfully.',
        );
    }

    public function store(StoreFriendRequestRequest $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $friendRequest = $this->friendConnectionService->sendRequest($user, $request->validated());

        return $this->success(
            data: (new FriendRequestResource($friendRequest))->resolve(),
            message: 'Friend request sent successfully.',
            status: 201,
        );
    }

    public function destroy(Request $request, FriendRequest $friendRequest): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $resolvedRequest = $this->friendConnectionService->decline($friendRequest, $user);

        return $this->success(
            data: (new FriendRequestResource($resolvedRequest))->resolve(),
            message: 'Friend request canceled successfully.',
        );
    }
}
