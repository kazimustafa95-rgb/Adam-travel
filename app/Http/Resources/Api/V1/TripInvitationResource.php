<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @mixin \App\Models\TripInvite
 */
class TripInvitationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $trip = $this->trip;
        $daysAgo = $this->created_at ? Carbon::parse($this->created_at)->diffForHumans(short: true, parts: 1) : null;

        return [
            'id' => $this->id,
            'email' => $this->email,
            'token' => $this->token,
            'role' => $this->role?->value,
            'status' => $this->status?->value,
            'expires_at' => optional($this->expires_at)?->toIso8601String(),
            'accepted_at' => optional($this->accepted_at)?->toIso8601String(),
            'sent_ago' => $daysAgo,
            'trip' => $this->whenLoaded('trip', fn () => [
                'id' => $trip?->id,
                'uuid' => $trip?->uuid,
                'title' => $trip?->title,
                'cover_image_url' => $trip?->cover_image_url,
                'start_date' => optional($trip?->start_date)?->toDateString(),
                'end_date' => optional($trip?->end_date)?->toDateString(),
                'member_count' => $trip?->relationLoaded('members') ? $trip?->members?->count() : $trip?->members_count,
                'date_range_label' => $trip?->getAttribute('date_range_label'),
                'primary_country_code' => $trip?->getAttribute('primary_country_code'),
                'primary_country_flag' => $trip?->getAttribute('primary_country_flag'),
            ]),
            'invited_by' => $this->whenLoaded('invitedBy', fn () => [
                'id' => $this->invitedBy?->id,
                'uuid' => $this->invitedBy?->uuid,
                'name' => $this->invitedBy?->name,
                'email' => $this->invitedBy?->email,
                'initials' => $this->invitedBy?->getAttribute('initials'),
            ]),
            'join_url' => route('api.v1.trip-invites.accept', ['token' => $this->token]),
        ];
    }
}
