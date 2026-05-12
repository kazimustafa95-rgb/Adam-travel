<?php

namespace App\Http\Requests\Api\V1\Billing;

use App\Enums\BillingProvider;
use App\Http\Requests\Api\V1\BaseApiRequest;
use Illuminate\Validation\Rule;

class RestoreSubscriptionRequest extends BaseApiRequest
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
            'provider' => ['required', Rule::in(BillingProvider::values())],
            'provider_app_user_id' => ['required', 'string', 'max:191'],
            'provider_product_id' => ['nullable', 'string', 'max:191'],
            'receipt_reference' => ['nullable', 'string', 'max:255'],
            'device_platform' => ['required', Rule::in(['ios', 'android'])],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
