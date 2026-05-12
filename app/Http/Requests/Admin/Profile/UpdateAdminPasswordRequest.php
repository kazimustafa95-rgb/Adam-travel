<?php

namespace App\Http\Requests\Admin\Profile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdateAdminPasswordRequest extends FormRequest
{
    protected $errorBag = 'passwordUpdate';

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
            'current_password' => ['required', 'string', 'current_password:admin'],
            'password' => ['required', 'string', 'confirmed', 'different:current_password', Password::defaults()],
        ];
    }
}
