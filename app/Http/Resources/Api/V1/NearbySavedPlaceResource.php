<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin array{saved_place: \App\Models\SavedPlace, distance_meters: int}
 */
class NearbySavedPlaceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'distance_meters' => $this['distance_meters'],
            'saved_place' => (new SavedPlaceResource($this['saved_place']))->resolve(),
        ];
    }
}
