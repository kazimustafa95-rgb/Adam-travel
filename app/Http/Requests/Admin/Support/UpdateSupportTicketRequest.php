<?php

namespace App\Http\Requests\Admin\Support;

use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupportTicketRequest extends FormRequest
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
            'status' => ['required', Rule::in(SupportTicketStatus::values())],
            'priority' => ['required', Rule::in(SupportTicketPriority::values())],
            'assigned_admin_id' => ['nullable', 'integer', 'exists:admins,id'],
            'admin_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
