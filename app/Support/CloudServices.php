<?php

namespace App\Support;

final class CloudServices
{
    public static function load(string $root): array
    {
        $path = $root.'/.tools/secrets/cloud-services.json';
        if (! is_file($path)) {
            throw new \RuntimeException('Save the connection details in the private cloud-services.json file first.');
        }
        $c = json_decode(ltrim(file_get_contents($path), "\xEF\xBB\xBF"), true, 512, JSON_THROW_ON_ERROR);
        self::validate($c);
        $c['tidb']['ca_file'] = ($c['tidb']['ca_file'] ?? '') ?: $root.'/.tools/php/cacert.pem';
        if (! is_readable($c['tidb']['ca_file'])) {
            throw new \RuntimeException('The configured database CA file is not readable.');
        }

        return $c;
    }

    public static function validate(array $c): void
    {
        foreach (['tidb' => ['host', 'username', 'password']] as $section => $keys) {
            foreach ($keys as $key) {
                if (! is_string($c[$section][$key] ?? null) || trim($c[$section][$key]) === '') {
                    throw new \RuntimeException('Missing private configuration field: '.$section.'.'.$key);
                }
            }
        }
        if (preg_match('/^[a-z0-9.-]+\.tidbcloud\.com$/D', $c['tidb']['host']) !== 1 || (int) ($c['tidb']['port'] ?? 0) !== 4000) {
            throw new \RuntimeException('The TiDB host/port must match the provider connection screen.');
        }
        self::validateStorage($c['supabase'] ?? []);
    }

    public static function loadStorage(string $root): array
    {
        $all = json_decode(ltrim(file_get_contents($root.'/.tools/secrets/cloud-services.json'), "\xEF\xBB\xBF"), true, 512, JSON_THROW_ON_ERROR);
        $c = $all['supabase'] ?? [];
        self::validateStorage($c);

        return $c;
    }

    public static function validateStorage(array $c): void
    {
        foreach (['endpoint', 'region', 'bucket', 'access_key_id', 'secret_access_key'] as $key) {
            if (! is_string($c[$key] ?? null) || trim($c[$key]) === '') {
                throw new \RuntimeException('Missing private configuration field: supabase.'.$key);
            }
        }
        $endpoint = parse_url($c['endpoint']);
        if (($endpoint['scheme'] ?? '') !== 'https' || preg_match('/^[a-z0-9-]+\.(storage\.)?supabase\.co$/D', $endpoint['host'] ?? '') !== 1
            || ($endpoint['path'] ?? '') !== '/storage/v1/s3' || isset($endpoint['user']) || isset($endpoint['pass']) || isset($endpoint['port']) || isset($endpoint['query']) || isset($endpoint['fragment'])) {
            throw new \RuntimeException('Use the Supabase S3 HTTPS endpoint without credentials or query parameters.');
        }
        if (preg_match('/^[a-z0-9][a-z0-9-]{1,61}[a-z0-9]$/D', $c['bucket']) !== 1) {
            throw new \RuntimeException('The private storage bucket name is invalid.');
        }
    }
}
