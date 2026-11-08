<?php

namespace App\Http\Requests;

use App\Rules\NotCommonPassword;
use Illuminate\Validation\Rules\Password;

class PasswordResetRequest extends LoginRequest
{
    public function resetFormUrl(): string
    {
        $token = $this->input('token');
        $email = $this->input('email');
        if (! is_string($token) || $token === '' || strlen($token) > 255) {
            return route('password.request');
        }

        return rtrim(config('app.url'), '/').'/reset-password/'.rawurlencode($token).'?email='.rawurlencode(is_string($email) ? mb_substr($email, 0, 255) : '');
    }

    protected function getRedirectUrl(): string
    {
        return $this->resetFormUrl();
    }

    public function rules(): array
    {
        return ['email' => ['required', 'email', 'max:255'], 'token' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:128', 'confirmed', Password::min(15), new NotCommonPassword]];
    }
}
