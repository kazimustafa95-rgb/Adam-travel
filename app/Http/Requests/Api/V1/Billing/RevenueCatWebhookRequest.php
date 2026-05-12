<?php

namespace App\Http\Requests\Api\V1\Billing;

use App\Http\Requests\Api\V1\BaseApiRequest;

class RevenueCatWebhookRequest extends BaseApiRequest
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
            'api_version' => ['nullable', 'string', 'max:20'],
            'event' => ['required', 'array'],
            'event.type' => ['required', 'string', 'max:100'],
            'event.app_user_id' => ['required', 'string', 'max:191'],
            'event.product_id' => ['required', 'string', 'max:191'],
            'event.original_transaction_id' => ['nullable', 'string', 'max:191'],
            'event.transaction_id' => ['nullable', 'string', 'max:191'],
            'event.store' => ['nullable', 'string', 'max:50'],
            'event.period_type' => ['nullable', 'string', 'max:50'],
            'event.event_timestamp_ms' => ['nullable', 'integer', 'min:0'],
            'event.purchased_at_ms' => ['nullable', 'integer', 'min:0'],
            'event.expiration_at_ms' => ['nullable', 'integer', 'min:0'],
            'event.grace_period_expiration_at_ms' => ['nullable', 'integer', 'min:0'],
            'event.entitlement_ids' => ['nullable', 'array'],
            'event.entitlement_ids.*' => ['string', 'max:100'],
        ];
    }
}
