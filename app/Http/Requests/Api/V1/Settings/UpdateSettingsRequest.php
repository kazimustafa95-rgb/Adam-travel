<?php

namespace App\Http\Requests\Api\V1\Settings;

use App\Http\Requests\Api\V1\BaseApiRequest;
use Illuminate\Validation\Rule;

class UpdateSettingsRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string|\Illuminate\Contracts\Validation\ValidationRule>|string>
     */
    public function rules(): array
    {
        return [
            'distance_unit' => ['sometimes', Rule::in(['km', 'mi'])],
            'map_style' => ['sometimes', 'nullable', 'string', 'max:50'],
            'default_radius_meters' => ['sometimes', 'integer', 'min:500', 'max:25000'],
            'notifications_enabled' => ['sometimes', 'boolean'],
            'offline_auto_sync' => ['sometimes', 'boolean'],
            'theme' => ['sometimes', 'nullable', Rule::in(['light', 'dark', 'system'])],
        ];
    }
}
