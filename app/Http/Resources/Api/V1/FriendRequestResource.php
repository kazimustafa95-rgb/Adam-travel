<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\FriendRequest
 */
class FriendRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'status' => $this->status?->value,
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'responded_at' => optional($this->responded_at)?->toIso8601String(),
            'sender' => $this->whenLoaded('sender', fn () => [
                'id' => $this->sender?->id,
                'uuid' => $this->sender?->uuid,
                'name' => $this->sender?->name,
                'email' => $this->sender?->email,
                'initials' => $this->sender?->getAttribute('initials'),
                'avatar_url' => $this->sender?->getAttribute('avatar_url'),
            ]),
            'recipient' => $this->whenLoaded('recipient', fn () => [
                'id' => $this->recipient?->id,
                'uuid' => $this->recipient?->uuid,
                'name' => $this->recipient?->name,
                'email' => $this->recipient?->email,
                'initials' => $this->recipient?->getAttribute('initials'),
                'avatar_url' => $this->recipient?->getAttribute('avatar_url'),
            ]),
        ];
    }
}
