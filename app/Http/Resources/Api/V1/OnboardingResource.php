<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\User
 */
class OnboardingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'completed' => $this->onboarding_completed_at !== null,
            'completed_at' => optional($this->onboarding_completed_at)?->toIso8601String(),
            'steps' => [
                [
                    'key' => 'save_locations',
                    'title' => 'Save travel spots quickly',
                    'description' => 'Import links or text and turn them into saved locations on your map.',
                ],
                [
                    'key' => 'organize_on_map',
                    'title' => 'Organize everything visually',
                    'description' => 'Group saved places into trips, categories, and route plans.',
                ],
                [
                    'key' => 'offline_access',
                    'title' => 'Keep plans available offline',
                    'description' => 'Access essential trip details even when connectivity drops.',
                ],
            ],
        ];
    }
}
