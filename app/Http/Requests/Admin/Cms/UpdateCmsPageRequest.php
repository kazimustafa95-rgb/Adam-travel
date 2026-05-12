<?php

namespace App\Http\Requests\Admin\Cms;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCmsPageRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'is_published' => ['sometimes', 'boolean'],
        ];
    }
}
