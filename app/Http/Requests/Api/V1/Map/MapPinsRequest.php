<?php

namespace App\Http\Requests\Api\V1\Map;

use App\Enums\SavedPlaceCategory;
use App\Http\Requests\Api\V1\BaseApiRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

class MapPinsRequest extends BaseApiRequest
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
            'north' => ['nullable', 'numeric', 'between:-90,90'],
            'south' => ['nullable', 'numeric', 'between:-90,90'],
            'east' => ['nullable', 'numeric', 'between:-180,180'],
            'west' => ['nullable', 'numeric', 'between:-180,180'],
            'category' => ['nullable', Rule::in(SavedPlaceCategory::values())],
            'saved_place_collection_id' => ['nullable', 'integer', 'exists:saved_place_collections,id'],
            'is_favorite' => ['nullable', 'boolean'],
            'q' => ['nullable', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'radius_meters' => ['nullable', 'integer', 'min:100', 'max:50000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $values = [
                $this->input('north'),
                $this->input('south'),
                $this->input('east'),
                $this->input('west'),
            ];

            $provided = array_filter($values, static fn ($value) => $value !== null && $value !== '');

            if ($provided !== [] && count($provided) !== 4) {
                $validator->errors()->add('bounds', 'North, south, east, and west must all be provided together.');
            }

            if ($this->filled('north') && $this->filled('south') && (float) $this->input('south') > (float) $this->input('north')) {
                $validator->errors()->add('south', 'South cannot be greater than north.');
            }

            if (($this->filled('latitude') xor $this->filled('longitude')) || ($this->filled('radius_meters') && ! $this->filled('latitude'))) {
                $validator->errors()->add('radius_meters', 'Latitude, longitude, and radius_meters must be used together.');
            }
        });
    }
}
