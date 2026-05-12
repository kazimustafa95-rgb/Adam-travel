<?php

namespace App\Http\Requests\Api\V1\Trips;

use App\Enums\TripStatus;
use App\Http\Requests\Api\V1\BaseApiRequest;
use Illuminate\Validation\Rule;

class TripIndexRequest extends BaseApiRequest
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
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(TripStatus::values())],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
