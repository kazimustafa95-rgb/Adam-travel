<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\OfflinePackage
 */
class OfflinePackageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'trip_id' => $this->trip_id,
            'package_scope' => $this->package_scope?->value,
            'scope_reference' => $this->scope_reference,
            'manifest_version' => $this->manifest_version,
            'status' => $this->status?->value,
            'manifest' => $this->manifest_payload,
            'expires_at' => optional($this->expires_at)?->toIso8601String(),
            'is_expired' => (bool) ($this->getAttribute('is_expired') ?? false),
            'is_stale' => (bool) ($this->getAttribute('is_stale') ?? false),
            'trip' => $this->whenLoaded('trip', fn () => (new TripResource($this->trip))->resolve()),
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
        ];
    }
}
