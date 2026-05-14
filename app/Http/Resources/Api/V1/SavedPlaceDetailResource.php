<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ImportCandidate;
use App\Models\SavedPlace;
use App\Models\TripPlace;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SavedPlace
 */
class SavedPlaceDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $base = (new SavedPlaceResource($this->resource))->resolve($request);
        $selectedCandidate = $this->selectedCandidate();

        $base['hero_image_url'] = data_get($this->location?->metadata, 'hero_image_url')
            ?? data_get($selectedCandidate?->metadata, 'hero_image_url');
        $base['preview_summary'] = $selectedCandidate?->summary
            ?? $this->notes
            ?? $this->location?->address_line
            ?? $this->location?->city;
        $base['selected_import_candidate'] = $selectedCandidate
            ? (new ImportCandidateResource($selectedCandidate))->resolve($request)
            : null;
        $base['trip_links'] = $this->whenLoaded('tripPlaces', function () use ($request): array {
            return $this->tripPlaces
                ->filter(fn (TripPlace $tripPlace) => $tripPlace->relationLoaded('trip') && $tripPlace->trip !== null)
                ->map(function (TripPlace $tripPlace) use ($request): array {
                    return [
                        'trip_place_id' => $tripPlace->id,
                        'trip_category' => $tripPlace->trip_category,
                        'notes' => $tripPlace->notes,
                        'trip' => (new TripResource($tripPlace->trip))->resolve($request),
                    ];
                })
                ->values()
                ->all();
        });
        $base['actions'] = [
            'can_categorize' => true,
            'can_add_to_trip' => true,
            'can_remove' => true,
        ];

        return $base;
    }

    protected function selectedCandidate(): ?ImportCandidate
    {
        if (! $this->relationLoaded('import') || $this->import === null || ! $this->import->relationLoaded('candidates')) {
            return null;
        }

        return $this->import->candidates
            ->first(fn (ImportCandidate $candidate) => $candidate->selected_at !== null)
            ?? $this->import->candidates->first();
    }
}
