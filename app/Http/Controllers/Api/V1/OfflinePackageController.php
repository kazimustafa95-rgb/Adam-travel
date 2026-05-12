<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\Offline\OfflinePackageIndexRequest;
use App\Http\Requests\Api\V1\Offline\StoreTripOfflinePackageRequest;
use App\Http\Resources\Api\V1\OfflinePackageResource;
use App\Models\Trip;
use App\Services\Offline\OfflinePackageService;
use Illuminate\Http\JsonResponse;

class OfflinePackageController extends BaseApiController
{
    public function __construct(protected OfflinePackageService $offlinePackageService)
    {
    }

    public function index(OfflinePackageIndexRequest $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $packages = $this->offlinePackageService->listForUser($user, $request->validated());

        return $this->success(
            data: OfflinePackageResource::collection($packages)->resolve(),
            message: 'Offline packages loaded successfully.',
            meta: [
                'count' => $packages->count(),
            ],
        );
    }

    public function storeTrip(StoreTripOfflinePackageRequest $request, Trip $trip): JsonResponse
    {
        $this->authorize('view', $trip);
        /** @var \App\Models\User $user */
        $user = $request->user();
        $package = $this->offlinePackageService->createTripPackage($user, $trip);

        return $this->success(
            data: (new OfflinePackageResource($package))->resolve(),
            message: 'Offline package created successfully.',
            status: 201,
        );
    }
}
