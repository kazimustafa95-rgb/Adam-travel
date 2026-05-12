<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\TripPlace
 */
class TripPlaceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currentUserId = $request->user()?->id;
        $heartedByCurrentUser = $this->relationLoaded('hearts')
            ? $this->hearts->contains('user_id', $currentUserId)
            : null;

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'trip_id' => $this->trip_id,
            'saved_place_id' => $this->saved_place_id,
            'added_by_user_id' => $this->added_by_user_id,
            'source' => $this->source?->value,
            'trip_category' => $this->trip_category,
            'notes' => $this->notes,
            'version' => $this->version,
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
            'hearts_count' => $this->whenCounted('hearts'),
            'hearted_by_current_user' => $heartedByCurrentUser,
            'saved_place' => $this->whenLoaded('savedPlace', fn () => (new SavedPlaceResource($this->savedPlace))->resolve()),
            'added_by' => $this->whenLoaded('addedBy', fn () => [
                'id' => $this->addedBy?->id,
                'uuid' => $this->addedBy?->uuid,
                'name' => $this->addedBy?->name,
            ]),
        ];
    }
}
