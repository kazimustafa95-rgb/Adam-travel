<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\UserPreference
 */
class UserPreferenceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'distance_unit' => $this->distance_unit,
            'map_style' => $this->map_style,
            'default_radius_meters' => $this->default_radius_meters,
            'notifications_enabled' => $this->notifications_enabled,
            'offline_auto_sync' => $this->offline_auto_sync,
            'theme' => $this->theme,
            'version' => $this->version,
        ];
    }
}
