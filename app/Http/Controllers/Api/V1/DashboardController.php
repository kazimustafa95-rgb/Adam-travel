<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\SavedPlaceResource;
use App\Services\Dashboard\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends BaseApiController
{
    public function __construct(protected DashboardService $dashboardService)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $dashboard = $this->dashboardService->buildForUser($user);

        return $this->success(
            data: [
                'summary' => $dashboard['summary'],
                'empty_states' => $dashboard['empty_states'],
                'recent_places' => SavedPlaceResource::collection($dashboard['recent_places'])->resolve(),
                'favorite_places' => SavedPlaceResource::collection($dashboard['favorite_places'])->resolve(),
                'map_summary' => $dashboard['map_summary'],
            ],
            message: 'Dashboard loaded successfully.',
        );
    }
}
