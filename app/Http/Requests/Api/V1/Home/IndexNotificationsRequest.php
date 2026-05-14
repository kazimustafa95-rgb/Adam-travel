<?php

namespace App\Http\Requests\Api\V1\Home;

use App\Http\Requests\Api\V1\BaseApiRequest;

class IndexNotificationsRequest extends BaseApiRequest
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
            'unread_only' => ['nullable', 'boolean'],
        ];
    }
}
