<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\SubscriptionPlan
 */
class SubscriptionPlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $features = $this->features_json ?? [];

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'provider_product_id' => $this->provider_product_id,
            'is_active' => (bool) $this->is_active,
            'is_free' => (float) $this->monthly_price <= 0.0 && (float) $this->yearly_price <= 0.0,
            'is_current' => (bool) $this->getAttribute('is_current'),
            'is_recommended' => (bool) $this->getAttribute('is_recommended'),
            'monthly_price' => (float) $this->monthly_price,
            'yearly_price' => (float) $this->yearly_price,
            'features' => $features,
            'benefits' => array_values(array_filter([
                isset($features['saved_places_limit']) ? 'Save up to '.$features['saved_places_limit'].' places.' : null,
                isset($features['offline_packages_limit']) ? 'Keep up to '.$features['offline_packages_limit'].' offline trip packages ready.' : null,
                ! empty($features['enhanced_ai']) ? 'Unlock enhanced AI planning signals.' : null,
            ])),
            'marketing_benefits' => $this->code === 'premium'
                ? [
                    'Unlimited map downloads',
                    'Offline navigation',
                    'Priority processing',
                    'AI travel suggestions',
                    'Shared trip collaboration',
                ]
                : [
                    'Core trip planning',
                    'Saved locations',
                    'Basic offline access',
                ],
        ];
    }
}
