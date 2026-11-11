<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}
$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';
try {
    $settings = json_decode(ltrim(file_get_contents($root.'/.tools/secrets/cloud-services.json'), "\xEF\xBB\xBF"), true, 512, JSON_THROW_ON_ERROR);
    $runtime = json_decode(file_get_contents($root.'/.tools/secrets/cloud-runtime.json'), true, 512, JSON_THROW_ON_ERROR);
    $values = Dotenv\Dotenv::parse(file_get_contents($root.'/deploy/cloud.env.example'));
    $values = array_replace($values, [
        'APP_KEY' => $runtime['app_key'], 'APP_URL' => $settings['app_url'] ?? '',
        'DB_HOST' => $runtime['connection']['host'], 'DB_USERNAME' => $runtime['connection']['username'], 'DB_PASSWORD' => $runtime['connection']['password'],
        'NFCHECK_DB_CA_BASE64' => base64_encode(file_get_contents($runtime['connection']['ca_file'])),
        'AWS_ENDPOINT' => $settings['supabase']['endpoint'], 'AWS_DEFAULT_REGION' => $settings['supabase']['region'], 'AWS_BUCKET' => $settings['supabase']['bucket'],
        'NFCHECK_S3_ACCESS_KEY_ID' => $settings['supabase']['access_key_id'], 'NFCHECK_S3_SECRET_ACCESS_KEY' => $settings['supabase']['secret_access_key'],
    ]);
    $providerPath = $root.'/.tools/secrets/ai-provider.json';
    $provider = is_file($providerPath) ? json_decode(file_get_contents($providerPath), true, 32, JSON_THROW_ON_ERROR) : [];
    if (($provider['enabled'] ?? false) === true && ($provider['free_tier_confirmed'] ?? false) === true && ($provider['allow_aggregate_workspaces'] ?? false) === true) {
        if (($provider['provider'] ?? '') !== 'gemini_free' || ($provider['model'] ?? '') !== 'gemini-3.1-flash-lite'
            || preg_match('/^[a-z][a-z0-9-]{4,61}[a-z0-9]$/D', $provider['project_id'] ?? '') !== 1
            || preg_match('/^[\x21-\x7E]{20,1024}$/D', $provider['api_key'] ?? '') !== 1) {
            throw new RuntimeException('The approved summary provider settings are incomplete.');
        }
        $values = array_replace($values, ['AI_SUMMARIES_ENABLED' => 'true', 'AI_SUMMARIES_ALLOW_AGGREGATES' => 'true', 'AI_SUMMARIES_FREE_TIER_CONFIRMED' => 'true', 'AI_SUMMARIES_INLINE' => 'true', 'AI_SUMMARIES_API_KEY' => $provider['api_key'], 'AI_SUMMARIES_PROJECT_ID' => $provider['project_id']]);
    }
    $path = $root.'/.tools/secrets/cloud-production.env';
    if (is_file($path)) {
        $old = Dotenv\Dotenv::parse(file_get_contents($path));
        foreach (['APP_URL', 'CRON_SECRET'] as $keep) {
            if ($values[$keep] === '') {
                $values[$keep] = $old[$keep] ?? '';
            }
        }
    }
    $lines = ['# Private server configuration. Never commit or paste this file into chat.'];
    foreach ($values as $name => $value) {
        $escaped = str_replace(['\\', '"', '$', "\r", "\n"], ['\\\\', '\\"', '\\$', '\\r', '\\n'], (string) $value);
        $lines[] = $name.'="'.$escaped.'"';
    }
    $text = implode("\n", $lines)."\n";
    if (Dotenv\Dotenv::parse($text) !== $values) {
        throw new RuntimeException('Environment serialization did not preserve the private values.');
    }
    if (file_put_contents($path, $text, LOCK_EX) === false) {
        throw new RuntimeException('Cannot save the private cloud environment.');
    }
    $aiLines = array_filter($lines, static fn (string $line): bool => str_starts_with($line, 'AI_SUMMARIES_'));
    if (file_put_contents($root.'/.tools/secrets/vercel-ai.env', implode("\n", $aiLines)."\n", LOCK_EX) === false) {
        throw new RuntimeException('Cannot save the private summary environment.');
    }
    echo json_encode(['saved' => true, 'private_file' => '.tools/secrets/cloud-production.env', 'app_url_set' => $values['APP_URL'] !== '', 'variable_count' => count($values)], JSON_PRETTY_PRINT), PHP_EOL;
} catch (Throwable $error) {
    echo json_encode(['saved' => false, 'error_type' => get_class($error)]), PHP_EOL;
    exit(1);
}
