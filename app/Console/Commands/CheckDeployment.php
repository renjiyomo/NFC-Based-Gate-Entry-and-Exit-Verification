<?php

namespace App\Console\Commands;

use App\Support\DeploymentChecks;
use Illuminate\Console\Command;
use Pdo\Mysql;

class CheckDeployment extends Command
{
    protected $signature = 'nfcheck:check-deployment {--json}';

    protected $description = 'Read-only configuration preflight; does not certify TLS, backups or university readiness';

    public function handle(DeploymentChecks $review): int
    {
        $database = config('database.connections.'.config('database.default'));
        $postgres = ($database['driver'] ?? '') === 'pgsql';
        $ca = $postgres ? ($database['sslrootcert'] ?? '') : ($database['options'][Mysql::ATTR_SSL_CA] ?? '');
        $checks = $review->evaluate([
            'environment' => app()->environment(), 'debug' => config('app.debug'), 'key' => config('app.key'), 'app_url' => config('app.url'),
            'preview' => config('nfcheck.ui_preview'), 'simulator' => config('nfcheck.nfc_simulator'), 'hardware' => config('nfcheck.hardware_lab'),
            'session_driver' => config('session.driver'), 'session_encrypt' => config('session.encrypt'), 'session_http_only' => config('session.http_only'), 'session_secure' => config('session.secure'), 'same_site' => config('session.same_site'), 'session_minutes' => config('session.lifetime'),
            'disk' => config('filesystems.default'), 'local_serve' => config('filesystems.disks.local.serve'), 'public_storage_link' => file_exists(public_path('storage')) || is_link(public_path('storage')),
            'photo_disk' => config('nfcheck.photo_disk'), 'cloud' => config('cloud.enabled'),
            's3_endpoint' => config('filesystems.disks.s3.endpoint'), 's3_visibility' => config('filesystems.disks.s3.visibility'), 's3_throw' => config('filesystems.disks.s3.throw'),
            's3_credentials' => (bool) config('filesystems.disks.s3.key') && (bool) config('filesystems.disks.s3.secret'), 's3_path_style' => config('filesystems.disks.s3.use_path_style_endpoint'), 's3_bucket' => config('filesystems.disks.s3.bucket'), 's3_region' => config('filesystems.disks.s3.region'),
            'cache_store' => config('cache.default'), 'db_ca_readable' => is_readable((string) $ca), 'db_verify_cert' => $postgres ? ($database['sslmode'] ?? '') === 'verify-full' : ($database['options'][Mysql::ATTR_SSL_VERIFY_SERVER_CERT] ?? false),
            'db_driver' => $database['driver'], 'db_schema' => $database['search_path'] ?? '', 'db_url_unset' => empty($database['url']), 'db_user' => $database['username'], 'trusted_proxies' => config('cloud.trusted_proxies'), 'password_recovery' => config('nfcheck.password_recovery'),
            'log_channel' => config('logging.default'), 'log_level' => config('logging.channels.'.config('logging.default').'.level'), 'log_days' => config('logging.channels.daily.max_files'),
            'ai_enabled' => config('ai_summaries.enabled'), 'private_ai_file' => is_file(base_path('.tools/secrets/ai-provider.json')),
            'ai_aggregate_scope' => config('ai_summaries.allow_aggregate_workspaces'), 'ai_free_tier' => config('ai_summaries.free_tier_confirmed'),
            'ai_provider' => config('ai_summaries.provider'), 'ai_model' => config('ai_summaries.model'), 'ai_inline' => config('ai_summaries.inline_generation'),
            'ai_daily_limit' => config('ai_summaries.max_requests_per_day'), 'ai_timeout' => config('ai_summaries.timeout_seconds'), 'ai_output_limit' => config('ai_summaries.max_output_tokens'),
            'built_assets' => is_file(public_path('build/manifest.json')), 'hot_file' => is_file(public_path('hot')),
        ]);
        $result = ['configuration_passed' => ! in_array(false, $checks, true), 'checks' => $checks, 'scope' => 'Configuration only; independently verify TLS, DB grants/migration target, mail, recovery, device/load evidence and institutional approval.'];
        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        } else {
            $this->table(['Check', 'Result'], array_map(fn ($name, $ok) => [$name, $ok ? 'PASS' : 'NEEDS REVIEW'], array_keys($checks), $checks));
            if (! ($checks['private_photo_storage'] ?? true) && config('cloud.enabled')) {
                $photoDisk = config('nfcheck.photo_disk') ?? config('filesystems.default');
                $endpoint = parse_url((string) config('filesystems.disks.s3.endpoint'));
                $this->warn('Private photo storage requires:');
                if ($photoDisk !== 's3') {
                    $this->line('  - NFCHECK_PHOTO_DISK=s3 (currently: '.($photoDisk ?: 'empty').')');
                }
                if (! (bool) config('filesystems.disks.s3.key') || ! (bool) config('filesystems.disks.s3.secret')) {
                    $this->line('  - Set NFCHECK_S3_ACCESS_KEY_ID and NFCHECK_S3_SECRET_ACCESS_KEY in Production, then deploy the updated source.');
                }
                if (empty(config('filesystems.disks.s3.bucket'))) {
                    $this->line('  - AWS_BUCKET must be set in Vercel (e.g. nfcheck-photos)');
                }
                if (empty(config('filesystems.disks.s3.region'))) {
                    $this->line('  - AWS_DEFAULT_REGION or AWS_REGION must be set in Vercel');
                }
                if (empty(config('filesystems.disks.s3.endpoint'))) {
                    $this->line('  - AWS_ENDPOINT must be set in Vercel');
                } elseif (($endpoint['scheme'] ?? '') !== 'https' || preg_match('/^[a-z0-9-]+\.(storage\.)?supabase\.co$/D', $endpoint['host'] ?? '') !== 1 || ($endpoint['path'] ?? '') !== '/storage/v1/s3') {
                    $this->line('  - AWS_ENDPOINT format must be https://<project-ref>.storage.supabase.co/storage/v1/s3 with no trailing slash');
                }
                if (! (bool) config('filesystems.disks.s3.use_path_style_endpoint')) {
                    $this->line('  - AWS_USE_PATH_STYLE_ENDPOINT=true must be set in Vercel');
                }
                if (file_exists(public_path('storage')) || is_link(public_path('storage'))) {
                    $this->line('  - public/storage symlink must not exist in container');
                }
            }
            $this->line($result['scope']);
        }

        return $result['configuration_passed'] ? self::SUCCESS : self::FAILURE;
    }
}
