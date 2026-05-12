<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\UserSubscription
 */
class UserSubscriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'status' => $this->status?->value,
            'provider' => $this->provider,
            'provider_product_id' => $this->provider_product_id,
            'auto_renews' => (bool) $this->auto_renews,
            'starts_at' => optional($this->starts_at)?->toIso8601String(),
            'trial_ends_at' => optional($this->trial_ends_at)?->toIso8601String(),
            'expires_at' => optional($this->expires_at)?->toIso8601String(),
            'grace_ends_at' => optional($this->grace_ends_at)?->toIso8601String(),
            'canceled_at' => optional($this->canceled_at)?->toIso8601String(),
            'last_synced_at' => optional($this->last_synced_at)?->toIso8601String(),
            'is_entitled' => (bool) $this->getAttribute('is_entitled'),
            'plan' => $this->whenLoaded('plan', fn () => (new SubscriptionPlanResource($this->plan))->resolve()),
        ];
    }
}
