<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\TripAiRun
 */
class TripAiRunResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'trip_id' => $this->trip_id,
            'requested_by_user_id' => $this->requested_by_user_id,
            'type' => $this->type?->value,
            'status' => $this->status?->value,
            'provider' => $this->provider,
            'model' => $this->model,
            'trip_version' => $this->trip_version,
            'input_hash' => $this->input_hash,
            'result' => $this->result_payload,
            'error_message' => $this->error_message,
            'applied_at' => optional($this->applied_at)?->toIso8601String(),
            'is_stale' => (bool) ($this->getAttribute('is_stale') ?? false),
            'was_cached' => (bool) ($this->getAttribute('was_cached') ?? false),
            'requested_by' => $this->whenLoaded('requestedBy', fn () => [
                'id' => $this->requestedBy?->id,
                'uuid' => $this->requestedBy?->uuid,
                'name' => $this->requestedBy?->name,
            ]),
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
        ];
    }
}
