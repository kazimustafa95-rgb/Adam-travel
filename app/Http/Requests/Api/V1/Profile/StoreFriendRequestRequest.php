<?php

namespace App\Http\Requests\Api\V1\Profile;

use App\Http\Requests\Api\V1\BaseApiRequest;
use Illuminate\Validation\Rule;

class StoreFriendRequestRequest extends BaseApiRequest
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
            'recipient_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'recipient_email' => ['nullable', 'email', 'max:255'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (! $this->filled('recipient_user_id') && ! $this->filled('recipient_email')) {
                $validator->errors()->add('recipient', 'Either recipient_user_id or recipient_email is required.');
            }
        });
    }
}
