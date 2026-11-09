<?php

namespace App\Support;

final class DeploymentChecks
{
    /** Configuration checks only: no network calls, secrets or operational approval. */
    public function evaluate(array $c): array
    {
        $url = parse_url((string) $c['app_url']);
        $host = strtolower($url['host'] ?? '');
        $realHost = $host !== '' && ! in_array($host, ['localhost', '127.0.0.1', '::1'], true) && ! str_ends_with($host, '.invalid');

        return [
            'production_environment' => $c['environment'] === 'production',
            'debug_disabled' => $c['debug'] === false,
            'application_key' => str_starts_with((string) $c['key'], 'base64:') && strlen(base64_decode(substr($c['key'], 7), true) ?: '') === 32,
            'https_origin' => ($url['scheme'] ?? '') === 'https' && $realHost && ! isset($url['user']) && ! isset($url['pass']) && ! isset($url['query']) && ! isset($url['fragment']),
            'lab_features_disabled' => ! $c['preview'] && ! $c['simulator'] && ! $c['hardware'],
            'private_sessions' => $c['session_driver'] === 'database' && $c['session_encrypt'] === true && $c['session_http_only'] === true && $c['session_secure'] === true && in_array($c['same_site'], ['lax', 'strict'], true) && $c['session_minutes'] > 0 && $c['session_minutes'] <= 30,
            'private_local_files' => $c['disk'] === 'local' && $c['local_serve'] === false && ! $c['public_storage_link'],
            'bounded_private_logging' => in_array($c['log_channel'], ['daily', 'stderr'], true) && in_array($c['log_level'], ['notice', 'warning', 'error', 'critical', 'alert', 'emergency'], true) && $c['log_days'] > 0 && $c['log_days'] <= 30,
            'live_ai_disabled' => $c['ai_enabled'] === false && ! $c['private_ai_file'],
            'built_assets_only' => $c['built_assets'] && ! $c['hot_file'],
        ];
    }
}
