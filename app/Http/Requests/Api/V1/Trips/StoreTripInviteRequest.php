<?php

namespace App\Http\Requests\Api\V1\Trips;

use App\Enums\TripMemberRole;
use App\Http\Requests\Api\V1\BaseApiRequest;
use Illuminate\Validation\Rule;

class StoreTripInviteRequest extends BaseApiRequest
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
            'email' => ['nullable', 'email'],
            'role' => ['required', Rule::in([
                TripMemberRole::Editor->value,
                TripMemberRole::Viewer->value,
            ])],
        ];
    }
}
