<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Location
 */
class LocationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'slug' => $this->slug,
            'category' => $this->category,
            'address_line' => $this->address_line,
            'city' => $this->city,
            'region' => $this->region,
            'country_code' => $this->country_code,
            'postal_code' => $this->postal_code,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'provider_place_id' => $this->provider_place_id,
            'provider_source' => $this->provider_source,
            'metadata' => $this->metadata ?? [],
        ];
    }
}
