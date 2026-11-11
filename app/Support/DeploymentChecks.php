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
        $photoDisk = $c['photo_disk'] ?? $c['disk'];
        $cloud = $c['cloud'] ?? false;
        $endpoint = parse_url((string) ($c['s3_endpoint'] ?? ''));
        $privateS3 = $photoDisk === 's3' && ($c['s3_visibility'] ?? '') === 'private' && ($c['s3_throw'] ?? false)
            && ($endpoint['scheme'] ?? '') === 'https' && preg_match('/^[a-z0-9-]+\.(storage\.)?supabase\.co$/D', $endpoint['host'] ?? '') === 1
            && ($endpoint['path'] ?? '') === '/storage/v1/s3' && ! isset($endpoint['user']) && ! isset($endpoint['pass']) && ! isset($endpoint['port']) && ! isset($endpoint['query']) && ! isset($endpoint['fragment'])
            && ($c['s3_credentials'] ?? false) && ($c['s3_path_style'] ?? false) && ($c['s3_bucket'] ?? '') !== '' && ($c['s3_region'] ?? '') !== '';

        $checks = [
            'production_environment' => $c['environment'] === 'production',
            'debug_disabled' => $c['debug'] === false,
            'application_key' => str_starts_with((string) $c['key'], 'base64:') && strlen(base64_decode(substr($c['key'], 7), true) ?: '') === 32,
            'https_origin' => ($url['scheme'] ?? '') === 'https' && $realHost && ! isset($url['user']) && ! isset($url['pass']) && ! isset($url['query']) && ! isset($url['fragment']),
            'lab_features_disabled' => ! $c['preview'] && ! $c['simulator'] && ! $c['hardware'],
            'private_sessions' => $c['session_driver'] === 'database' && $c['session_encrypt'] === true && $c['session_http_only'] === true && $c['session_secure'] === true && in_array($c['same_site'], ['lax', 'strict'], true) && $c['session_minutes'] > 0 && $c['session_minutes'] <= 30,
            'private_photo_storage' => ! $c['public_storage_link'] && ($cloud ? $privateS3 : ($photoDisk === 'local' && $c['local_serve'] === false)),
            'bounded_private_logging' => in_array($c['log_channel'], ['daily', 'stderr'], true) && in_array($c['log_level'], ['notice', 'warning', 'error', 'critical', 'alert', 'emergency'], true) && $c['log_days'] > 0 && $c['log_days'] <= 30,
            'ai_data_controls' => ! $c['private_ai_file'] && ($c['ai_enabled'] === false || (
                ($c['ai_aggregate_scope'] ?? false) === true && ($c['ai_free_tier'] ?? false) === true
                && ($c['ai_provider'] ?? '') === 'gemini_free' && ($c['ai_model'] ?? '') === 'gemini-3.1-flash-lite'
                && ($c['ai_inline'] ?? false) === true && ($c['ai_daily_limit'] ?? 0) === 3
                && ($c['ai_timeout'] ?? 0) === 12 && ($c['ai_output_limit'] ?? 0) === 600
            )),
            'built_assets_only' => $c['built_assets'] && ! $c['hot_file'],
        ];
        if ($cloud) {
            $checks['shared_cache'] = ($c['cache_store'] ?? '') === 'database';
            $checks['verified_database_tls'] = ($c['db_ca_readable'] ?? false) && ($c['db_verify_cert'] ?? false) === true && ($c['db_url_unset'] ?? false);
            $checks['postgresql_application_schema'] = ($c['db_driver'] ?? '') === 'pgsql' && ($c['db_schema'] ?? '') === 'nfcheck_online';
            $checks['restricted_database_account'] = preg_match('/^nfcheck_app(?:_[a-f0-9]+)?(?:\.[a-z0-9]+)?$/D', (string) ($c['db_user'] ?? '')) === 1;
            $checks['explicit_proxy_configuration'] = ($c['trusted_proxies'] ?? []) !== [];
            $checks['email_recovery_disabled'] = ($c['password_recovery'] ?? true) === false;
        }

        return $checks;
    }
}
