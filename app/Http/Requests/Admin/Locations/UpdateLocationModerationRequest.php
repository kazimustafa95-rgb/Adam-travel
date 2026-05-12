<?php

namespace App\Http\Requests\Admin\Locations;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLocationModerationRequest extends FormRequest
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
            'is_moderated_hidden' => ['required', 'boolean'],
        ];
    }
}
