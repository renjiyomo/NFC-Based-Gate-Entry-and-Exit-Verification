<?php

namespace App\Support;

final class DevelopmentRequestLog
{
    public static function path(string $uri): string
    {
        $path = rawurldecode(parse_url($uri, PHP_URL_PATH) ?: '/');
        if (in_array($path, ['/', '/up', '/login', '/logout', '/forgot-password'], true)) {
            return $path;
        }
        if (str_starts_with($path, '/reset-password/')) {
            return '/reset-password/[redacted]';
        }
        if (preg_match('#^/(admin|guard|simulator|account|nfc|preview)/([^/]+)(/.*)?$#D', $path, $m)) {
            $allowed = [
                'admin' => ['dashboard', 'students', 'employees', 'gates', 'guards', 'choices', 'credentials', 'history', 'analytics', 'reports', 'anomalies', 'summaries', 'settings'],
                'guard' => ['scanner', 'activity', 'gates'],
                'simulator' => ['issue', 'destination', 'check', 'credentials', 'guard'],
                'account' => ['password'], 'nfc' => ['setup'],
                'preview' => ['login', 'dashboard', 'students', 'employees', 'gates', 'guards', 'credentials', 'history', 'analytics', 'reports', 'settings', 'scanner'],
            ];
            if (in_array($m[2], $allowed[$m[1]], true)) {
                return '/'.$m[1].'/'.$m[2].(isset($m[3]) ? '/[redacted]' : '');
            }
        }

        return '/[unmatched]';
    }

    public static function method(string $method): string
    {
        return in_array($method, ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], true) ? $method : 'OTHER';
    }
}
