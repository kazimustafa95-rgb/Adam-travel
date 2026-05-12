<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\ImportCandidate
 */
class ImportCandidateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'candidate_rank' => $this->candidate_rank,
            'place_name' => $this->place_name,
            'category' => $this->category,
            'city' => $this->city,
            'region' => $this->region,
            'country' => $this->country,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'provider_place_id' => $this->provider_place_id,
            'summary' => $this->summary,
            'confidence_score' => $this->confidence_score !== null ? (float) $this->confidence_score : null,
            'metadata' => $this->metadata ?? [],
            'selected_at' => optional($this->selected_at)?->toIso8601String(),
        ];
    }
}
