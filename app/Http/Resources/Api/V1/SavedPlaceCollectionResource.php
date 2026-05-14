<?php

namespace App\Http\Resources\Api\V1;

use App\Models\SavedPlaceCollection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SavedPlaceCollection
 */
class SavedPlaceCollectionResource extends JsonResource
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
            'description' => $this->description,
            'color_hex' => $this->color_hex,
            'sort_order' => $this->sort_order,
            'saved_places_count' => $this->whenCounted('savedPlaces'),
            'saved_places' => $this->whenLoaded('savedPlaces', fn () => SavedPlaceResource::collection($this->savedPlaces)->resolve()),
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
        ];
    }
}
