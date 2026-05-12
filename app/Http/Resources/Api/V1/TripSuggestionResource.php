<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\TripSuggestion
 */
class TripSuggestionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'trip_id' => $this->trip_id,
            'trip_ai_run_id' => $this->trip_ai_run_id,
            'saved_place_id' => $this->saved_place_id,
            'location_id' => $this->location_id,
            'title' => $this->title,
            'category' => $this->category,
            'summary' => $this->summary,
            'score' => $this->score !== null ? (float) $this->score : null,
            'distance_meters' => $this->distance_meters,
            'status' => $this->status?->value,
            'reasons' => $this->raw_payload['reasons'] ?? [],
            'saved_place' => $this->whenLoaded('savedPlace', fn () => (new SavedPlaceResource($this->savedPlace))->resolve()),
            'location' => $this->whenLoaded('location', fn () => (new LocationResource($this->location))->resolve()),
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
        ];
    }
}
