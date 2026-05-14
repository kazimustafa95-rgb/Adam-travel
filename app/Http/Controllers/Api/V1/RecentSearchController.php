<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\Home\StoreRecentSearchRequest;
use App\Http\Resources\Api\V1\RecentSearchResource;
use App\Models\User;
use App\Services\Home\HomeSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecentSearchController extends BaseApiController
{
    public function __construct(protected HomeSearchService $homeSearchService) {}

    public function store(StoreRecentSearchRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $search = $this->homeSearchService->storeRecentSearch(
            $user,
            $request->string('q')->toString(),
            $request->validated('result_count'),
        );

        return $this->success(
            data: (new RecentSearchResource($search))->resolve(),
            message: 'Recent search saved successfully.',
            status: 201,
        );
    }

    public function destroy(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->homeSearchService->clearRecentSearches($user);

        return $this->success(
            message: 'Recent searches cleared successfully.',
        );
    }
}
