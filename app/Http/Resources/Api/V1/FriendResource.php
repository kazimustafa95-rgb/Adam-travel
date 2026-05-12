<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Friendship
 */
class FriendResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'connected_at' => optional($this->connected_at)?->toIso8601String(),
            'friend' => $this->whenLoaded('friend', fn () => [
                'id' => $this->friend?->id,
                'uuid' => $this->friend?->uuid,
                'name' => $this->friend?->name,
                'email' => $this->friend?->email,
                'initials' => $this->friend?->getAttribute('initials'),
                'avatar_url' => $this->friend?->getAttribute('avatar_url'),
            ]),
        ];
    }
}
