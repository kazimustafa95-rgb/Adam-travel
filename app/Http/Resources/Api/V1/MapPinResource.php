<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\SavedPlace
 */
class MapPinResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'title' => $this->title_override ?: $this->location?->name,
            'category' => $this->category?->value,
            'is_favorite' => $this->is_favorite,
            'region_label' => $this->region_label,
            'latitude' => $this->location?->latitude !== null ? (float) $this->location->latitude : null,
            'longitude' => $this->location?->longitude !== null ? (float) $this->location->longitude : null,
            'city' => $this->location?->city,
            'country_code' => $this->location?->country_code,
            'location_name' => $this->location?->name,
        ];
    }
}
