<?php

namespace App\Services\Billing;

use App\Enums\BillingProvider;
use App\Enums\SubscriptionEventType;
use App\Enums\SubscriptionStatus;
use App\Models\OfflinePackage;
use App\Models\SavedPlace;
use App\Models\SubscriptionEvent;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SubscriptionService
{
    /**
     * @return Collection<int, SubscriptionPlan>
     */
    public function activePlansForUser(User $user): Collection
    {
        $currentPlanCode = $this->effectivePlanForUser($user)->code;

        $plans = SubscriptionPlan::query()
            ->where('is_active', true)
            ->orderBy('monthly_price')
            ->get();

        $plans->each(function (SubscriptionPlan $plan) use ($currentPlanCode): void {
            $plan->setAttribute('is_current', $plan->code === $currentPlanCode);
            $plan->setAttribute('is_recommended', $plan->code === 'premium');
        });

        return $plans;
    }

    public function activeSubscriptionForUser(User $user): UserSubscription|null
    {
        $subscriptions = UserSubscription::query()
            ->where('user_id', $user->id)
            ->with('plan')
            ->orderByDesc(DB::raw('COALESCE(expires_at, created_at)'))
            ->orderByDesc('id')
            ->get();

        foreach ($subscriptions as $subscription) {
            if ($this->shouldExpire($subscription)) {
                $subscription->forceFill([
                    'status' => SubscriptionStatus::Expired,
                    'last_synced_at' => now(),
                ])->save();
            }
        }

        return $subscriptions->first(fn (UserSubscription $subscription): bool => $this->isEntitled($subscription));
    }

    public function effectivePlanForUser(User $user): SubscriptionPlan
    {
        $subscription = $this->activeSubscriptionForUser($user);

        if ($subscription?->plan) {
            return $subscription->plan;
        }

        return SubscriptionPlan::query()->where('code', 'free')->first()
            ?? new SubscriptionPlan([
                'code' => 'free',
                'name' => 'Free',
                'is_active' => true,
                'monthly_price' => 0,
                'yearly_price' => 0,
                'features_json' => [
                    'saved_places_limit' => 50,
                    'offline_packages_limit' => 1,
                    'enhanced_ai' => false,
                ],
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function currentSummary(User $user): array
    {
        $subscription = $this->activeSubscriptionForUser($user);
        $plan = $subscription?->plan ?? $this->effectivePlanForUser($user);
        $usage = $this->usageSummary($user, $plan);
        $features = $plan->features_json ?? [];

        return [
            'subscription' => $subscription?->setAttribute('is_entitled', $this->isEntitled($subscription)),
            'plan' => $plan,
            'entitlements' => [
                'plan_code' => $plan->code,
                'enhanced_ai' => (bool) ($features['enhanced_ai'] ?? false),
                'limits' => [
                    'saved_places' => $usage['saved_places']['limit'],
                    'offline_packages' => $usage['offline_packages']['limit'],
                ],
            ],
            'usage' => $usage,
            'paywall' => [
                'should_show' => $plan->code === 'free',
                'recommended_plan_code' => 'premium',
                'reasons' => array_values(array_filter([
                    $usage['saved_places']['remaining'] !== null && $usage['saved_places']['remaining'] <= 5 ? 'You are close to your saved places limit.' : null,
                    $usage['offline_packages']['remaining'] !== null && $usage['offline_packages']['remaining'] <= 0 ? 'You have reached your offline package limit.' : null,
                    ! (bool) ($features['enhanced_ai'] ?? false) ? 'Premium unlocks enhanced AI planning signals.' : null,
                ])),
            ],
        ];
    }

    public function featureLimit(User $user, string $key, int $default): int
    {
        $value = $this->featureValue($user, $key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    public function featureEnabled(User $user, string $key, bool $default = false): bool
    {
        return (bool) $this->featureValue($user, $key, $default);
    }

    public function assertCanCreateSavedPlace(User $user): void
    {
        $summary = $this->currentSummary($user);
        $savedPlaces = $summary['usage']['saved_places'];

        if ($savedPlaces['limit'] !== null && $savedPlaces['used'] >= $savedPlaces['limit']) {
            throw ValidationException::withMessages([
                'subscription' => ['Your current plan has reached its saved places limit.'],
            ]);
        }
    }

    public function recordRestoreRequest(User $user, array $payload): SubscriptionEvent
    {
        if (! in_array((string) $payload['provider_app_user_id'], [$user->uuid, (string) $user->id, $user->email], true)) {
            throw ValidationException::withMessages([
                'provider_app_user_id' => ['The restore request user identifier does not match the authenticated account.'],
            ]);
        }

        $plan = null;

        if (! empty($payload['provider_product_id'])) {
            $plan = $this->planForProductId((string) $payload['provider_product_id']);
        }

        return SubscriptionEvent::query()->create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan?->id,
            'provider' => $payload['provider'],
            'event_type' => SubscriptionEventType::RestoreRequested->value,
            'event_hash' => (string) Str::uuid(),
            'external_event_id' => $payload['receipt_reference'] ?? null,
            'event_payload' => $payload,
            'occurred_at' => now(),
            'processed_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function checkoutPreview(User $user, array $payload): array
    {
        $plan = SubscriptionPlan::query()
            ->where('code', (string) $payload['plan_code'])
            ->where('is_active', true)
            ->firstOrFail();

        $billingCycle = (string) $payload['billing_cycle'];
        $baseAmount = $billingCycle === 'yearly'
            ? (float) $plan->yearly_price
            : (float) $plan->monthly_price;
        $taxRate = (float) ($payload['tax_rate'] ?? 0);
        $taxAmount = round($baseAmount * $taxRate, 2);
        $total = round($baseAmount + $taxAmount, 2);

        return [
            'plan' => $plan,
            'billing_cycle' => $billingCycle,
            'subtotal' => $baseAmount,
            'tax_amount' => $taxAmount,
            'tax_rate' => $taxRate,
            'total_today' => $total,
            'next_billing_at' => $billingCycle === 'yearly'
                ? now()->addYear()->toDateString()
                : now()->addMonth()->toDateString(),
            'payment_method' => [
                'brand' => strtolower((string) ($payload['payment_method_brand'] ?? 'visa')),
                'last4' => (string) ($payload['payment_method_last4'] ?? '4242'),
                'can_change' => true,
            ],
            'legal' => [
                'secure_copy' => 'Secured with 256-bit encryption',
                'cancel_copy' => 'Cancel anytime',
            ],
            'current_plan_code' => $this->effectivePlanForUser($user)->code,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function activationSummary(User $user): array
    {
        $summary = $this->currentSummary($user);
        $plan = $summary['plan'];
        $subscription = $summary['subscription'];
        $price = $plan->code === 'premium' ? (float) $plan->monthly_price : 0.0;

        return [
            'is_active' => $plan->code !== 'free',
            'headline' => $plan->code === 'free' ? 'Upgrade to Premium' : 'Subscription Activated',
            'message' => $plan->code === 'free'
                ? 'Unlock offline maps, AI suggestions, unlimited downloads, and more.'
                : 'Welcome to Premium. You now have full access to offline maps, AI suggestions, unlimited downloads and more.',
            'badge' => $plan->code === 'free' ? null : 'Premium · $'.number_format($price, 2).'/month',
            'benefits' => $this->marketingBenefits($plan),
            'subscription' => $subscription,
            'plan' => $plan,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{event: SubscriptionEvent, subscription: UserSubscription, user: User}
     */
    public function syncRevenueCatEvent(array $payload): array
    {
        $eventPayload = $payload['event'];
        $eventType = $this->normalizeEventType((string) $eventPayload['type']);
        $user = $this->resolveUserFromProviderIdentifier((string) $eventPayload['app_user_id']);
        $plan = $this->planForProductId((string) $eventPayload['product_id']);
        $occurredAt = $this->timestampFromMilliseconds($eventPayload['event_timestamp_ms'] ?? $eventPayload['purchased_at_ms'] ?? null) ?? now();
        $expiresAt = $this->timestampFromMilliseconds($eventPayload['expiration_at_ms'] ?? null);
        $graceEndsAt = $this->timestampFromMilliseconds($eventPayload['grace_period_expiration_at_ms'] ?? null);
        $periodType = (string) ($eventPayload['period_type'] ?? 'normal');
        $eventHash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use (
            $eventHash,
            $payload,
            $eventPayload,
            $eventType,
            $user,
            $plan,
            $occurredAt,
            $expiresAt,
            $graceEndsAt,
            $periodType,
        ): array {
            $existingEvent = SubscriptionEvent::query()
                ->where('event_hash', $eventHash)
                ->with(['subscription.plan', 'user'])
                ->first();

            if ($existingEvent && $existingEvent->subscription && $existingEvent->user) {
                return [
                    'event' => $existingEvent,
                    'subscription' => $existingEvent->subscription,
                    'user' => $existingEvent->user,
                ];
            }

            $subscription = $this->findOrCreateSubscription($user, $plan, $eventPayload);
            $status = $this->statusForRevenueCatEvent($eventType, $periodType, $expiresAt, $graceEndsAt);

            $subscription->fill([
                'subscription_plan_id' => $plan->id,
                'user_id' => $user->id,
                'provider' => BillingProvider::RevenueCat->value,
                'provider_product_id' => $eventPayload['product_id'],
                'provider_customer_id' => $eventPayload['app_user_id'],
                'provider_subscription_id' => $eventPayload['transaction_id'] ?? $subscription->provider_subscription_id,
                'provider_original_transaction_id' => $eventPayload['original_transaction_id'] ?? $subscription->provider_original_transaction_id,
                'status' => $status,
                'starts_at' => $subscription->starts_at ?? $occurredAt,
                'trial_ends_at' => $periodType === 'trial' ? $expiresAt : $subscription->trial_ends_at,
                'expires_at' => $expiresAt,
                'grace_ends_at' => $graceEndsAt,
                'canceled_at' => $eventType === SubscriptionEventType::Cancellation ? $occurredAt : ($eventType === SubscriptionEventType::Uncancellation ? null : $subscription->canceled_at),
                'auto_renews' => ! in_array($eventType, [SubscriptionEventType::Cancellation, SubscriptionEventType::Expiration], true),
                'last_synced_at' => now(),
                'metadata' => [
                    'store' => $eventPayload['store'] ?? null,
                    'period_type' => $periodType,
                    'entitlement_ids' => $eventPayload['entitlement_ids'] ?? [],
                    'raw_type' => $eventPayload['type'],
                ],
            ]);
            $subscription->save();

            $event = SubscriptionEvent::query()->create([
                'user_id' => $user->id,
                'user_subscription_id' => $subscription->id,
                'subscription_plan_id' => $plan->id,
                'provider' => BillingProvider::RevenueCat->value,
                'event_type' => $eventType->value,
                'event_hash' => $eventHash,
                'external_event_id' => $eventPayload['transaction_id'] ?? $eventPayload['original_transaction_id'] ?? null,
                'event_payload' => $payload,
                'occurred_at' => $occurredAt,
                'processed_at' => now(),
            ]);

            return [
                'event' => $event,
                'subscription' => $subscription->fresh('plan'),
                'user' => $user,
            ];
        });
    }

    protected function featureValue(User $user, string $key, mixed $default = null): mixed
    {
        $plan = $this->effectivePlanForUser($user);
        $features = $plan->features_json ?? [];

        return $features[$key] ?? $default;
    }

    /**
     * @return list<string>
     */
    public function marketingBenefits(SubscriptionPlan $plan): array
    {
        if ($plan->code !== 'premium') {
            return [
                'Essential saved places',
                'Basic offline downloads',
                'Core trip planning',
            ];
        }

        return [
            'Unlimited map downloads',
            'Offline navigation',
            'AI travel suggestions',
            'Priority processing',
            'Shared trip collaboration',
        ];
    }

    protected function isEntitled(UserSubscription $subscription): bool
    {
        if (! in_array($subscription->status, [SubscriptionStatus::Trialing, SubscriptionStatus::Active, SubscriptionStatus::Grace], true)) {
            return false;
        }

        if ($subscription->status === SubscriptionStatus::Grace) {
            return $subscription->grace_ends_at?->isFuture() ?? false;
        }

        return $subscription->expires_at?->isFuture() ?? true;
    }

    protected function shouldExpire(UserSubscription $subscription): bool
    {
        if (in_array($subscription->status, [SubscriptionStatus::Expired], true)) {
            return false;
        }

        if ($subscription->status === SubscriptionStatus::Grace) {
            return ! ($subscription->grace_ends_at?->isFuture() ?? false);
        }

        return $subscription->expires_at !== null && $subscription->expires_at->isPast();
    }

    /**
     * @return array<string, array<string, int|null>>
     */
    protected function usageSummary(User $user, SubscriptionPlan $plan): array
    {
        $features = $plan->features_json ?? [];
        $savedPlacesUsed = SavedPlace::query()->where('user_id', $user->id)->count();
        $offlineUsed = OfflinePackage::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['queued', 'ready'])
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->count();

        $savedPlacesLimit = isset($features['saved_places_limit']) ? (int) $features['saved_places_limit'] : null;
        $offlineLimit = isset($features['offline_packages_limit']) ? (int) $features['offline_packages_limit'] : null;

        return [
            'saved_places' => [
                'used' => $savedPlacesUsed,
                'limit' => $savedPlacesLimit,
                'remaining' => $savedPlacesLimit !== null ? max(0, $savedPlacesLimit - $savedPlacesUsed) : null,
            ],
            'offline_packages' => [
                'used' => $offlineUsed,
                'limit' => $offlineLimit,
                'remaining' => $offlineLimit !== null ? max(0, $offlineLimit - $offlineUsed) : null,
            ],
        ];
    }

    protected function normalizeEventType(string $type): SubscriptionEventType
    {
        $normalized = Str::of($type)
            ->lower()
            ->replace([' ', '-'], '_')
            ->toString();

        return SubscriptionEventType::tryFrom($normalized)
            ?? SubscriptionEventType::Renewal;
    }

    protected function resolveUserFromProviderIdentifier(string $identifier): User
    {
        $query = User::query();
        $user = $query->where('uuid', $identifier)->first();

        if (! $user && is_numeric($identifier)) {
            $user = User::query()->find((int) $identifier);
        }

        if (! $user) {
            $user = User::query()->where('email', $identifier)->first();
        }

        if (! $user) {
            throw ValidationException::withMessages([
                'event.app_user_id' => ['The billing event references an unknown user.'],
            ]);
        }

        return $user;
    }

    protected function planForProductId(string $productId): SubscriptionPlan
    {
        $plan = SubscriptionPlan::query()
            ->where('provider_product_id', $productId)
            ->orWhere('code', $productId)
            ->first();

        if (! $plan) {
            throw ValidationException::withMessages([
                'event.product_id' => ['The billing event references an unknown plan product.'],
            ]);
        }

        return $plan;
    }

    /**
     * @param  array<string, mixed>  $eventPayload
     */
    protected function findOrCreateSubscription(User $user, SubscriptionPlan $plan, array $eventPayload): UserSubscription
    {
        $originalTransactionId = $eventPayload['original_transaction_id'] ?? null;
        $transactionId = $eventPayload['transaction_id'] ?? null;

        if ($originalTransactionId) {
            $subscription = UserSubscription::query()
                ->where('provider', BillingProvider::RevenueCat->value)
                ->where('provider_original_transaction_id', $originalTransactionId)
                ->first();

            if ($subscription) {
                return $subscription;
            }
        }

        if ($transactionId) {
            $subscription = UserSubscription::query()
                ->where('provider', BillingProvider::RevenueCat->value)
                ->where('provider_subscription_id', $transactionId)
                ->first();

            if ($subscription) {
                return $subscription;
            }
        }

        return new UserSubscription([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'provider' => BillingProvider::RevenueCat->value,
        ]);
    }

    protected function statusForRevenueCatEvent(
        SubscriptionEventType $eventType,
        string $periodType,
        Carbon|null $expiresAt,
        Carbon|null $graceEndsAt,
    ): SubscriptionStatus {
        if ($eventType === SubscriptionEventType::Expiration || ($expiresAt && $expiresAt->isPast() && ! ($graceEndsAt?->isFuture() ?? false))) {
            return SubscriptionStatus::Expired;
        }

        if ($eventType === SubscriptionEventType::BillingIssue || ($graceEndsAt?->isFuture() ?? false)) {
            return SubscriptionStatus::Grace;
        }

        if ($periodType === 'trial') {
            return SubscriptionStatus::Trialing;
        }

        return SubscriptionStatus::Active;
    }

    protected function timestampFromMilliseconds(int|string|null $value): Carbon|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::createFromTimestampMs((int) $value);
    }
}
