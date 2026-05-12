<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Import
 */
class ImportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'source_type' => $this->source_type?->value,
            'source_url' => $this->source_url,
            'source_host' => $this->source_host,
            'raw_text' => $this->raw_text,
            'normalized_text' => $this->normalized_text,
            'status' => $this->status?->value,
            'error_code' => $this->error_code,
            'error_message' => $this->error_message,
            'confidence_score' => $this->confidence_score !== null ? (float) $this->confidence_score : null,
            'processed_at' => optional($this->processed_at)?->toIso8601String(),
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
            'saved_place_id' => $this->whenLoaded('savedPlaces', fn () => optional($this->savedPlaces->first())->id),
            'requires_manual_review' => $this->status?->value === 'manual_review',
            'can_confirm' => $this->status?->value === 'awaiting_confirmation',
            'candidates' => $this->whenLoaded('candidates', fn () => ImportCandidateResource::collection($this->candidates)->resolve()),
        ];
    }
}
