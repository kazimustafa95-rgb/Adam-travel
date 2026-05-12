<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $socialAccounts = $this->relationLoaded('socialAccounts') ? $this->socialAccounts : collect();

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'email' => $this->email,
            'initials' => str($this->name)
                ->explode(' ')
                ->filter()
                ->take(2)
                ->map(fn (string $part) => str($part)->substr(0, 1)->upper()->toString())
                ->implode(''),
            'avatar_url' => $socialAccounts->pluck('avatar_url')->filter()->first(),
            'social_providers' => $socialAccounts
                ->pluck('provider')
                ->filter()
                ->map(fn ($provider) => $provider?->value ?? (string) $provider)
                ->values()
                ->all(),
            'status' => $this->status?->value,
            'email_verified_at' => optional($this->email_verified_at)?->toIso8601String(),
            'onboarding_completed_at' => optional($this->onboarding_completed_at)?->toIso8601String(),
            'last_seen_at' => optional($this->last_seen_at)?->toIso8601String(),
            'preference' => $this->whenLoaded('preference', fn () => (new UserPreferenceResource($this->preference))->resolve()),
        ];
    }
}
