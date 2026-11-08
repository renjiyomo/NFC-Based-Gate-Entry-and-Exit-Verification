<?php

namespace App\Http\Requests;

class ForgotPasswordRequest extends LoginRequest
{
    protected $redirectRoute = 'password.request';

    public function rules(): array
    {
        return ['email' => ['required', 'email', 'max:255']];
    }
}
