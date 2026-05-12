<?php

namespace App\Http\Requests\Api\V1\Billing;

use App\Http\Requests\Api\V1\BaseApiRequest;
use Illuminate\Validation\Rule;

class SubscriptionCheckoutPreviewRequest extends BaseApiRequest
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
            'plan_code' => ['required', 'string', Rule::exists('subscription_plans', 'code')->where('is_active', true)],
            'billing_cycle' => ['required', Rule::in(['monthly', 'yearly'])],
            'payment_method_brand' => ['sometimes', 'nullable', 'string', 'max:30'],
            'payment_method_last4' => ['sometimes', 'nullable', 'string', 'size:4'],
            'tax_rate' => ['sometimes', 'numeric', 'min:0', 'max:1'],
        ];
    }
}
