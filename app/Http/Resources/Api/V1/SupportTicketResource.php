<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\SupportTicket
 */
class SupportTicketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'subject' => $this->subject,
            'message' => $this->message,
            'priority' => $this->priority?->value,
            'status' => $this->status?->value,
            'admin_notes' => $this->admin_notes,
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
            'resolved_at' => optional($this->resolved_at)?->toIso8601String(),
        ];
    }
}
