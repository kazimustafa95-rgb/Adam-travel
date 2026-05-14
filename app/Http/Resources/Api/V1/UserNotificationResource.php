<?php

namespace App\Http\Resources\Api\V1;

use App\Models\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin UserNotification
 */
class UserNotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'type' => $this->type,
            'title' => $this->title,
            'body' => $this->body,
            'tag' => $this->tag,
            'priority' => $this->priority,
            'is_read' => $this->is_read,
            'payload' => $this->payload ?? [],
            'sent_at' => optional($this->sent_at)?->toIso8601String(),
            'read_at' => optional($this->read_at)?->toIso8601String(),
        ];
    }
}
