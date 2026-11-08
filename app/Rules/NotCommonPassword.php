<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NotCommonPassword implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $normalized = mb_strtolower(trim((string) $value));
        if (preg_match('/^(.)\1+$/us', $normalized) || in_array($normalized, [
            'passwordpassword', 'passwordpasswordpassword', '123456789012345', '1234567890123456',
            'qwertyuiopasdfgh', 'qwertyuiopasdfghjkl', 'correct horse battery staple',
            'this is my password', 'nfcheckpassword123',
        ], true)) {
            $fail('Choose a less predictable password or a unique passphrase.');
        }
    }
}
