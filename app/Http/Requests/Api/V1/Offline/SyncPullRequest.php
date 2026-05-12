<?php

namespace App\Http\Requests\Api\V1\Offline;

use App\Http\Requests\Api\V1\BaseApiRequest;

class SyncPullRequest extends BaseApiRequest
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
            'cursor' => ['nullable', 'date'],
            'device_identifier' => ['required', 'string', 'max:255'],
            'device_name' => ['sometimes', 'string', 'max:255'],
            'device_platform' => ['sometimes', 'string', 'max:50'],
        ];
    }
}
