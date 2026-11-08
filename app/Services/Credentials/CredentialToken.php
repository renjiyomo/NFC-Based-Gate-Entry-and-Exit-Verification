<?php

namespace App\Services\Credentials;

class CredentialToken
{
    public function generate(): string
    {
        return 'nfcheck:v1:'.rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    public function digest(#[\SensitiveParameter] string $payload): ?string
    {
        if (! preg_match('/\Anfcheck:v1:([A-Za-z0-9_-]{43})\z/D', $payload, $matches)) {
            return null;
        }
        $bytes = base64_decode(strtr($matches[1], '-_', '+/').'=', true);
        if ($bytes === false || strlen($bytes) !== 32 || rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=') !== $matches[1]) {
            return null;
        }

        return hash('sha256', $bytes, true);
    }
}
