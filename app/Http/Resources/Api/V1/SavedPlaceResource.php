<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\SavedPlace
 */
class SavedPlaceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $displayTitle = $this->title_override ?: $this->location?->name;

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'title' => $displayTitle,
            'title_override' => $this->title_override,
            'notes' => $this->notes,
            'category' => $this->category?->value,
            'region_label' => $this->region_label,
            'is_favorite' => $this->is_favorite,
            'visibility' => $this->visibility,
            'version' => $this->version,
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
            'location' => $this->whenLoaded('location', fn () => (new LocationResource($this->location))->resolve()),
            'import_id' => $this->import_id,
        ];
    }
}
