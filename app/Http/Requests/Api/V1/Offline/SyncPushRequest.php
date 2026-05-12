<?php

namespace App\Http\Requests\Api\V1\Offline;

use App\Http\Requests\Api\V1\BaseApiRequest;
use Illuminate\Validation\Rule;

class SyncPushRequest extends BaseApiRequest
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
            'device_identifier' => ['required', 'string', 'max:255'],
            'device_name' => ['sometimes', 'string', 'max:255'],
            'device_platform' => ['sometimes', 'string', 'max:50'],
            'changes' => ['required', 'array', 'min:1'],
            'changes.*.entity' => ['required', Rule::in(['user_preference', 'saved_place'])],
            'changes.*.action' => ['required', Rule::in(['update', 'delete'])],
            'changes.*.record_id' => ['nullable', 'integer'],
            'changes.*.version' => ['nullable', 'integer', 'min:1'],
            'changes.*.payload' => ['nullable', 'array'],
        ];
    }
}
