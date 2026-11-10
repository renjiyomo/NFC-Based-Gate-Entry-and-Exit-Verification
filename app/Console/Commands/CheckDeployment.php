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
            'built_assets' => is_file(public_path('build/manifest.json')), 'hot_file' => is_file(public_path('hot')),
        ]);
        $result = ['configuration_passed' => ! in_array(false, $checks, true), 'checks' => $checks, 'scope' => 'Configuration only; independently verify TLS, DB grants/migration target, mail, recovery, device/load evidence and institutional approval.'];
        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        } else {
            $this->table(['Check', 'Result'], array_map(fn ($name, $ok) => [$name, $ok ? 'PASS' : 'NEEDS REVIEW'], array_keys($checks), $checks));
            $this->line($result['scope']);
        }

        return $result['configuration_passed'] ? self::SUCCESS : self::FAILURE;
    }
}
