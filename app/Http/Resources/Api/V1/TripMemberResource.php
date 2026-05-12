<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\TripMember
 */
class TripMemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'role' => $this->role?->value,
            'joined_at' => optional($this->joined_at)?->toIso8601String(),
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user?->id,
                'uuid' => $this->user?->uuid,
                'name' => $this->user?->name,
                'email' => $this->user?->email,
            ]),
        ];
    }
}
