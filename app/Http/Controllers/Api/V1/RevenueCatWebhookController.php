<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\Billing\RevenueCatWebhookRequest;
use App\Services\Billing\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class RevenueCatWebhookController extends BaseApiController
{
    public function __construct(protected SubscriptionService $subscriptionService)
    {
    }

    public function __invoke(RevenueCatWebhookRequest $request): JsonResponse
    {
        $this->guardSignature($request->getContent(), (string) $request->header('X-RevenueCat-Signature'));
        $payload = $this->subscriptionService->syncRevenueCatEvent($request->validated());

        return $this->success(
            data: [
                'event_id' => $payload['event']->id,
                'subscription_id' => $payload['subscription']->id,
                'user_id' => $payload['user']->id,
            ],
            message: 'Billing webhook processed successfully.',
            status: 202,
        );
    }

    protected function guardSignature(string $rawPayload, string $providedSignature): void
    {
        $secret = (string) config('services.revenuecat.webhook_secret');

        if ($secret === '') {
            throw new AccessDeniedHttpException('Billing webhook secret is not configured.');
        }

        $expectedSignature = hash_hmac('sha256', $rawPayload, $secret);

        if ($providedSignature === '' || ! hash_equals($expectedSignature, $providedSignature)) {
            throw new AccessDeniedHttpException('Invalid billing webhook signature.');
        }
    }
}
