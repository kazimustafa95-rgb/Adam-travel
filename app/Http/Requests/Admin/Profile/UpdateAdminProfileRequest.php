<?php

namespace App\Http\Requests\Admin\Profile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdminProfileRequest extends FormRequest
{
    protected $errorBag = 'profileUpdate';

    public function authorize(): bool
    {
        return $this->user('admin') !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('admins', 'email')
                    ->ignore($this->user('admin'))
                    ->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
        ];
    }
}
