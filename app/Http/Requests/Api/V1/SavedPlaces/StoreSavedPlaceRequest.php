<?php

namespace App\Http\Requests\Api\V1\SavedPlaces;

use App\Enums\SavedPlaceCategory;
use App\Http\Requests\Api\V1\BaseApiRequest;
use Illuminate\Validation\Rule;

class StoreSavedPlaceRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'location' => ['required_without:location_id', 'array'],
            'location.name' => ['required_without:location_id', 'string', 'max:255'],
            'location.slug' => ['nullable', 'string', 'max:255'],
            'location.category' => ['nullable', 'string', 'max:50'],
            'location.address_line' => ['nullable', 'string', 'max:255'],
            'location.city' => ['nullable', 'string', 'max:100'],
            'location.region' => ['nullable', 'string', 'max:100'],
            'location.country_code' => ['nullable', 'string', 'size:2'],
            'location.postal_code' => ['nullable', 'string', 'max:30'],
            'location.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'location.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'location.provider_place_id' => ['nullable', 'string', 'max:191'],
            'location.provider_source' => ['nullable', 'string', 'max:50'],
            'location.metadata' => ['nullable', 'array'],
            'title_override' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'category' => ['required', Rule::in(SavedPlaceCategory::values())],
            'region_label' => ['nullable', 'string', 'max:100'],
            'is_favorite' => ['sometimes', 'boolean'],
            'visibility' => ['sometimes', Rule::in(['private', 'trip_shared'])],
        ];
    }
}
