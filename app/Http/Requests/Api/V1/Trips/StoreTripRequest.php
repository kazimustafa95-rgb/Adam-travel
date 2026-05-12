<?php

namespace App\Http\Requests\Api\V1\Trips;

use App\Enums\TripStatus;
use App\Http\Requests\Api\V1\BaseApiRequest;
use Illuminate\Validation\Rule;

class StoreTripRequest extends BaseApiRequest
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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'start_location_name' => ['required', 'string', 'max:255'],
            'start_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'start_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'end_location_name' => ['required', 'string', 'max:255'],
            'end_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'end_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'status' => ['sometimes', Rule::in(TripStatus::values())],
            'cover_image_url' => ['nullable', 'url:http,https', 'max:2048'],
        ];
    }
}
