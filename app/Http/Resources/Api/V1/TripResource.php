<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\TripMemberRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Trip
 */
class TripResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currentUserId = $request->user()?->id;
        $currentRole = null;

        if ($this->relationLoaded('members')) {
            $currentRole = $this->members->firstWhere('user_id', $currentUserId)?->role?->value;
        }

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'start_location_name' => $this->start_location_name,
            'start_latitude' => $this->start_latitude !== null ? (float) $this->start_latitude : null,
            'start_longitude' => $this->start_longitude !== null ? (float) $this->start_longitude : null,
            'end_location_name' => $this->end_location_name,
            'end_latitude' => $this->end_latitude !== null ? (float) $this->end_latitude : null,
            'end_longitude' => $this->end_longitude !== null ? (float) $this->end_longitude : null,
            'start_date' => optional($this->start_date)?->toDateString(),
            'end_date' => optional($this->end_date)?->toDateString(),
            'status' => $this->status?->value,
            'cover_image_url' => $this->cover_image_url,
            'version' => $this->version,
            'owner_user_id' => $this->owner_user_id,
            'current_user_role' => $currentRole,
            'member_count' => $this->whenCounted('members'),
            'pool_count' => $this->whenCounted('pool'),
            'pending_invites_count' => $this->when(isset($this->pending_invites_count), $this->pending_invites_count),
            'owner' => $this->whenLoaded('owner', fn () => [
                'id' => $this->owner?->id,
                'uuid' => $this->owner?->uuid,
                'name' => $this->owner?->name,
                'email' => $this->owner?->email,
            ]),
            'members' => $this->whenLoaded('members', fn () => TripMemberResource::collection($this->members)->resolve()),
            'invites' => $this->whenLoaded('invites', fn () => TripInviteResource::collection($this->invites)->resolve()),
            'pool' => $this->whenLoaded('pool', fn () => TripPlaceResource::collection($this->pool)->resolve()),
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
        ];
    }
}
