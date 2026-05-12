<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\Offline\CheckProximityRequest;
use App\Http\Resources\Api\V1\NearbySavedPlaceResource;
use App\Services\Offline\ProximityService;
use Illuminate\Http\JsonResponse;

class ProximityController extends BaseApiController
{
    public function __construct(protected ProximityService $proximityService)
    {
    }

    public function __invoke(CheckProximityRequest $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $payload = $this->proximityService->check($user, $request->validated());

        return $this->success(
            data: [
                'should_prompt' => $payload['should_prompt'],
                'cooldown_active' => $payload['cooldown_active'],
                'cooldown_minutes' => $payload['cooldown_minutes'],
                'radius_meters' => $payload['radius_meters'],
                'next_eligible_at' => $payload['next_eligible_at'],
                'nearby_places' => NearbySavedPlaceResource::collection(collect($payload['nearby_places']))->resolve(),
            ],
            message: 'Proximity check completed successfully.',
            meta: [
                'count' => count($payload['nearby_places']),
            ],
        );
    }
}
