<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\TripStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @mixin \App\Models\Trip
 */
class TimelineTripResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $startDate = $this->start_date ? Carbon::parse($this->start_date) : null;
        $endDate = $this->end_date ? Carbon::parse($this->end_date) : null;
        $primaryCountryCode = $this->getAttribute('primary_country_code');

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'title' => $this->title,
            'status' => $this->status?->value,
            'start_date' => optional($this->start_date)?->toDateString(),
            'end_date' => optional($this->end_date)?->toDateString(),
            'date_range_label' => $this->getAttribute('date_range_label'),
            'nights_count' => $startDate && $endDate ? $startDate->diffInDays($endDate) : null,
            'places_count' => $this->whenCounted('pool'),
            'member_count' => $this->whenCounted('members'),
            'cover_image_url' => $this->cover_image_url,
            'start_location_name' => $this->start_location_name,
            'end_location_name' => $this->end_location_name,
            'primary_country_code' => $primaryCountryCode,
            'primary_country_flag' => $this->flagEmoji($primaryCountryCode),
            'is_read_only' => true,
            'timeline_status_label' => in_array($this->status, [TripStatus::Archived, TripStatus::Completed], true) || (($endDate?->isPast()) ?? false)
                ? 'Done'
                : 'Past',
            'owner' => $this->whenLoaded('owner', fn () => [
                'id' => $this->owner?->id,
                'uuid' => $this->owner?->uuid,
                'name' => $this->owner?->name,
                'email' => $this->owner?->email,
            ]),
            'itinerary_days' => $this->whenLoaded('itineraryDays', fn () => ItineraryDayResource::collection($this->itineraryDays)->resolve()),
        ];
    }

    protected function flagEmoji(string|null $countryCode): string|null
    {
        if (! is_string($countryCode) || strlen($countryCode) !== 2) {
            return null;
        }

        $countryCode = strtoupper($countryCode);
        $offset = 127397;

        return mb_chr(ord($countryCode[0]) + $offset).mb_chr(ord($countryCode[1]) + $offset);
    }
}
