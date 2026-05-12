<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\ItineraryItem
 */
class ItineraryItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'itinerary_day_id' => $this->itinerary_day_id,
            'trip_place_id' => $this->trip_place_id,
            'scheduled_by_user_id' => $this->scheduled_by_user_id,
            'source' => $this->source?->value,
            'starts_at' => optional($this->starts_at)?->toIso8601String(),
            'ends_at' => optional($this->ends_at)?->toIso8601String(),
            'sort_order' => $this->sort_order,
            'notes' => $this->notes,
            'version' => $this->version,
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
            'trip_place' => $this->whenLoaded('tripPlace', fn () => (new TripPlaceResource($this->tripPlace))->resolve()),
            'scheduled_by' => $this->whenLoaded('scheduledBy', fn () => [
                'id' => $this->scheduledBy?->id,
                'uuid' => $this->scheduledBy?->uuid,
                'name' => $this->scheduledBy?->name,
            ]),
        ];
    }
}
