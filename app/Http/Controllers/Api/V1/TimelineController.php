<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\TimelineTripResource;
use App\Models\Trip;
use App\Services\Trips\TimelineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TimelineController extends BaseApiController
{
    public function __construct(protected TimelineService $timelineService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:30'],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();
        $paginator = $this->timelineService->paginateForUser($user, $validated);

        return $this->success(
            data: TimelineTripResource::collection($paginator->items())->resolve(),
            message: 'Timeline loaded successfully.',
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function show(Request $request, Trip $trip): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        return $this->success(
            data: (new TimelineTripResource($this->timelineService->detailForUser($trip, $user)))->resolve(),
            message: 'Timeline trip loaded successfully.',
        );
    }
}
