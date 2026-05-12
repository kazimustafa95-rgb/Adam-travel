<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\ItineraryDay
 */
class ItineraryDayResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'trip_id' => $this->trip_id,
            'day_number' => $this->day_number,
            'trip_date' => optional($this->trip_date)?->toDateString(),
            'title' => $this->title,
            'notes' => $this->notes,
            'version' => $this->version,
            'item_count' => $this->whenLoaded('items', fn () => $this->items->count()),
            'items' => $this->whenLoaded('items', fn () => ItineraryItemResource::collection($this->items)->resolve()),
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
        ];
    }
}
