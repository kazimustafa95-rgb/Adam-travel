<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\Home\HomeSearchRequest;
use App\Models\User;
use App\Services\Home\HomeSearchService;
use Illuminate\Http\JsonResponse;

class HomeSearchController extends BaseApiController
{
    public function __construct(protected HomeSearchService $homeSearchService) {}

    public function __invoke(HomeSearchRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->success(
            data: $this->homeSearchService->payloadForUser($user, $request->validated()),
            message: 'Home search loaded successfully.',
        );
    }
}
