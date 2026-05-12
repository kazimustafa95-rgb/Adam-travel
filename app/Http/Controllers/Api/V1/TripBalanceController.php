<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Trip;
use App\Services\Trips\TripBalanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TripBalanceController extends BaseApiController
{
    public function __construct(protected TripBalanceService $tripBalanceService)
    {
    }

    public function __invoke(Request $request, Trip $trip): JsonResponse
    {
        $this->authorize('viewBalance', $trip);

        return $this->success(
            data: $this->tripBalanceService->summarize($trip),
            message: 'Trip balance loaded successfully.',
        );
    }
}
