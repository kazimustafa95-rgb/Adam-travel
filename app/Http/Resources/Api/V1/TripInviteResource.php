<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\TripInvite
 */
class TripInviteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'token' => $this->token,
            'role' => $this->role?->value,
            'status' => $this->status?->value,
            'expires_at' => optional($this->expires_at)?->toIso8601String(),
            'accepted_at' => optional($this->accepted_at)?->toIso8601String(),
            'join_url' => route('api.v1.trip-invites.accept', ['token' => $this->token]),
        ];
    }
}
