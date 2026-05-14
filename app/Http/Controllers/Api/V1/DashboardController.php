<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\Dashboard\HomeDashboardRequest;
use App\Http\Resources\Api\V1\SavedPlaceResource;
use App\Models\User;
use App\Services\Dashboard\DashboardService;
use Illuminate\Http\JsonResponse;

class DashboardController extends BaseApiController
{
    public function __construct(protected DashboardService $dashboardService) {}

    public function __invoke(HomeDashboardRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $dashboard = $this->dashboardService->buildForUser($user, $request->validated());

        return $this->success(
            data: [
                'summary' => $dashboard['summary'],
                'empty_states' => $dashboard['empty_states'],
                'quick_actions' => $dashboard['quick_actions'],
                'recent_places' => SavedPlaceResource::collection($dashboard['recent_places'])->resolve(),
                'favorite_places' => SavedPlaceResource::collection($dashboard['favorite_places'])->resolve(),
                'collections' => $dashboard['collections'],
                'map_summary' => $dashboard['map_summary'],
                'filters' => $dashboard['filters'],
                'notifications' => $dashboard['notifications'],
                'smart_banner' => $dashboard['smart_banner'],
                'search' => $dashboard['search'],
            ],
            message: 'Dashboard loaded successfully.',
        );
    }
}
