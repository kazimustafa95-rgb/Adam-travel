<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\Billing\SubscriptionCheckoutPreviewRequest;
use App\Http\Requests\Api\V1\Billing\RestoreSubscriptionRequest;
use App\Http\Resources\Api\V1\SubscriptionPlanResource;
use App\Http\Resources\Api\V1\UserSubscriptionResource;
use App\Services\Billing\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends BaseApiController
{
    public function __construct(protected SubscriptionService $subscriptionService)
    {
    }

    public function show(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $summary = $this->subscriptionService->currentSummary($user);

        return $this->success(
            data: [
                'subscription' => $summary['subscription']
                    ? (new UserSubscriptionResource($summary['subscription']->loadMissing('plan')))->resolve()
                    : null,
                'plan' => (new SubscriptionPlanResource($summary['plan']))->resolve(),
                'entitlements' => $summary['entitlements'],
                'usage' => $summary['usage'],
                'paywall' => $summary['paywall'],
            ],
            message: 'Subscription details loaded successfully.',
        );
    }

    public function restore(RestoreSubscriptionRequest $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $event = $this->subscriptionService->recordRestoreRequest($user, $request->validated());
        $summary = $this->subscriptionService->currentSummary($user);

        return $this->success(
            data: [
                'subscription' => $summary['subscription']
                    ? (new UserSubscriptionResource($summary['subscription']->loadMissing('plan')))->resolve()
                    : null,
                'plan' => (new SubscriptionPlanResource($summary['plan']))->resolve(),
                'entitlements' => $summary['entitlements'],
                'usage' => $summary['usage'],
            ],
            message: 'Subscription restore request recorded successfully.',
            meta: [
                'refresh_requested' => true,
                'restore_event_id' => $event->id,
            ],
            status: 202,
        );
    }

    public function checkoutPreview(SubscriptionCheckoutPreviewRequest $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $preview = $this->subscriptionService->checkoutPreview($user, $request->validated());

        return $this->success(
            data: [
                'plan' => (new SubscriptionPlanResource($preview['plan']))->resolve(),
                'billing_cycle' => $preview['billing_cycle'],
                'subtotal' => $preview['subtotal'],
                'tax_amount' => $preview['tax_amount'],
                'tax_rate' => $preview['tax_rate'],
                'total_today' => $preview['total_today'],
                'next_billing_at' => $preview['next_billing_at'],
                'payment_method' => $preview['payment_method'],
                'legal' => $preview['legal'],
                'current_plan_code' => $preview['current_plan_code'],
            ],
            message: 'Subscription checkout preview loaded successfully.',
        );
    }

    public function activated(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $summary = $this->subscriptionService->activationSummary($user);

        return $this->success(
            data: [
                'is_active' => $summary['is_active'],
                'headline' => $summary['headline'],
                'message' => $summary['message'],
                'badge' => $summary['badge'],
                'benefits' => $summary['benefits'],
                'plan' => (new SubscriptionPlanResource($summary['plan']))->resolve(),
                'subscription' => $summary['subscription']
                    ? (new UserSubscriptionResource($summary['subscription']->loadMissing('plan')))->resolve()
                    : null,
            ],
            message: 'Subscription activation summary loaded successfully.',
        );
    }
}
