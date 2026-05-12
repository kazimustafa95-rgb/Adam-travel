<?php

namespace App\Services\Imports;

use App\Enums\ImportSourceType;
use App\Enums\ImportStatus;
use App\Enums\SavedPlaceCategory;
use App\Jobs\Imports\ProcessImportJob;
use App\Models\Import;
use App\Models\ImportCandidate;
use App\Models\User;
use App\Services\SavedPlaces\SavedPlaceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ImportService
{
    public function __construct(protected SavedPlaceService $savedPlaceService)
    {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(User $user, array $payload): Import
    {
        $sourceType = ! empty($payload['source_url']) ? ImportSourceType::Url : ImportSourceType::Text;

        $import = Import::query()->create([
            'user_id' => $user->id,
            'source_type' => $sourceType,
            'source_url' => $payload['source_url'] ?? null,
            'source_host' => ! empty($payload['source_url']) ? parse_url((string) $payload['source_url'], PHP_URL_HOST) : null,
            'raw_text' => $payload['raw_text'] ?? null,
            'status' => ImportStatus::Pending,
        ]);

        ProcessImportJob::dispatch($import->id);

        return $import->fresh(['candidates', 'savedPlaces']);
    }

    public function retry(Import $import): Import
    {
        $import->update([
            'status' => ImportStatus::Pending,
            'error_code' => null,
            'error_message' => null,
            'confidence_score' => null,
        ]);

        ProcessImportJob::dispatch($import->id);

        return $import->fresh(['candidates', 'savedPlaces']);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function manualOverride(Import $import, array $payload): Import
    {
        return DB::transaction(function () use ($import, $payload): Import {
            $import->candidates()->delete();

            $import->candidates()->create([
                'candidate_rank' => 1,
                'place_name' => $payload['place_name'],
                'category' => $payload['category'] ?? SavedPlaceCategory::Other->value,
                'city' => $payload['city'] ?? null,
                'region' => $payload['region'] ?? null,
                'country' => $payload['country'] ?? null,
                'latitude' => $payload['latitude'],
                'longitude' => $payload['longitude'],
                'provider_place_id' => $payload['provider_place_id'] ?? null,
                'summary' => $payload['summary'] ?? $payload['place_name'].' manually corrected by the user.',
                'confidence_score' => 1.00,
                'metadata' => [
                    'processor' => 'manual_override',
                ],
            ]);

            $import->update([
                'source_type' => $import->source_type === ImportSourceType::Url ? $import->source_type : ImportSourceType::Manual,
                'status' => ImportStatus::AwaitingConfirmation,
                'error_code' => null,
                'error_message' => null,
                'confidence_score' => 1.00,
                'processed_at' => now(),
            ]);

            return $import->fresh(['candidates', 'savedPlaces']);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{import: \App\Models\Import, saved_place: \App\Models\SavedPlace}
     */
    public function confirm(Import $import, array $payload): array
    {
        if ($import->status !== ImportStatus::AwaitingConfirmation) {
            throw ValidationException::withMessages([
                'import' => ['This import is not ready to be confirmed yet.'],
            ]);
        }

        $candidate = $this->resolveCandidate($import, $payload['candidate_id'] ?? null);

        if ($candidate->latitude === null || $candidate->longitude === null) {
            throw ValidationException::withMessages([
                'candidate' => ['The selected candidate must have coordinates before it can be confirmed.'],
            ]);
        }

        return DB::transaction(function () use ($import, $candidate, $payload): array {
            $savedPlace = $this->savedPlaceService->create($import->user, [
                'import_id' => $import->id,
                'location' => [
                    'name' => $candidate->place_name,
                    'slug' => Str::slug($candidate->place_name),
                    'category' => $candidate->category,
                    'city' => $candidate->city,
                    'region' => $candidate->region,
                    'country_code' => $this->normalizeCountryCode($candidate->country),
                    'latitude' => $candidate->latitude,
                    'longitude' => $candidate->longitude,
                    'provider_place_id' => $candidate->provider_place_id,
                    'provider_source' => 'import',
                    'metadata' => array_filter([
                        'summary' => $candidate->summary,
                        'country' => $candidate->country,
                    ]),
                ],
                'title_override' => $payload['title_override'] ?? null,
                'notes' => $payload['notes'] ?? null,
                'category' => $payload['category'] ?? $this->normalizeSavedPlaceCategory($candidate->category),
                'region_label' => $payload['region_label'] ?? null,
                'is_favorite' => (bool) ($payload['is_favorite'] ?? false),
                'visibility' => $payload['visibility'] ?? 'private',
            ]);

            $candidate->forceFill([
                'selected_at' => now(),
            ])->save();

            $import->update([
                'status' => ImportStatus::Completed,
                'processed_at' => now(),
                'confidence_score' => $candidate->confidence_score,
            ]);

            return [
                'import' => $import->fresh(['candidates', 'savedPlaces']),
                'saved_place' => $savedPlace,
            ];
        });
    }

    protected function resolveCandidate(Import $import, int|null $candidateId): ImportCandidate
    {
        $candidate = $candidateId
            ? $import->candidates()->whereKey($candidateId)->first()
            : $import->candidates()->orderBy('candidate_rank')->first();

        if (! $candidate) {
            throw ValidationException::withMessages([
                'candidate_id' => ['A valid import candidate is required.'],
            ]);
        }

        return $candidate;
    }

    protected function normalizeCountryCode(?string $country): ?string
    {
        if ($country === null) {
            return null;
        }

        $trimmed = trim($country);

        return Str::length($trimmed) === 2 ? strtoupper($trimmed) : null;
    }

    protected function normalizeSavedPlaceCategory(?string $category): string
    {
        return in_array($category, SavedPlaceCategory::values(), true)
            ? (string) $category
            : SavedPlaceCategory::Other->value;
    }
}
