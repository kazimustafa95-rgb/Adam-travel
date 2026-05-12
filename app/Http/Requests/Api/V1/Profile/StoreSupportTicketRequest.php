<?php

namespace App\Http\Requests\Api\V1\Profile;

use App\Enums\SupportTicketPriority;
use App\Http\Requests\Api\V1\BaseApiRequest;
use Illuminate\Validation\Rule;

class StoreSupportTicketRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string|\Illuminate\Contracts\Validation\ValidationRule>>
     */
    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:180'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'priority' => ['sometimes', Rule::in(SupportTicketPriority::values())],
            'metadata' => ['sometimes', 'array'],
        ];
    }
}
