<?php

namespace App\Http\Requests\Api\V1\Trips;

use App\Enums\TripStatus;
use App\Http\Requests\Api\V1\BaseApiRequest;
use Illuminate\Validation\Rule;

class UpdateTripRequest extends BaseApiRequest
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
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'start_location_name' => ['sometimes', 'string', 'max:255'],
            'start_latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'start_longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'end_location_name' => ['sometimes', 'string', 'max:255'],
            'end_latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'end_longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date'],
            'status' => ['sometimes', Rule::in(TripStatus::values())],
            'cover_image_url' => ['sometimes', 'nullable', 'url:http,https', 'max:2048'],
        ];
    }
}
