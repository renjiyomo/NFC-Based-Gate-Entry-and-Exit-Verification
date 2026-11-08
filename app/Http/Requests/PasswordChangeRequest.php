<?php

namespace App\Http\Requests;

use App\Rules\NotCommonPassword;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class PasswordChangeRequest extends FormRequest
{
    protected $redirectRoute = 'password.edit';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['current_password' => ['required', 'string', 'max:128'],
            'password' => ['required', 'string', 'confirmed', 'max:128', 'different:current_password', Password::min(15), new NotCommonPassword]];
    }
}
