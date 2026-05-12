<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\SubscriptionPlanResource;
use App\Services\Billing\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlanController extends BaseApiController
{
    public function __construct(protected SubscriptionService $subscriptionService)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $plans = $this->subscriptionService->activePlansForUser($user);
        $summary = $this->subscriptionService->currentSummary($user);

        return $this->success(
            data: SubscriptionPlanResource::collection($plans)->resolve(),
            message: 'Subscription plans loaded successfully.',
            meta: [
                'count' => $plans->count(),
                'current_plan_code' => $summary['plan']->code,
                'recommended_plan_code' => 'premium',
            ],
        );
    }
}
