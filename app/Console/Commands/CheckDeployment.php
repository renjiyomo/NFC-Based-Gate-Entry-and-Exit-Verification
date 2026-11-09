<?php

namespace App\Console\Commands;

use App\Support\DeploymentChecks;
use Illuminate\Console\Command;

class CheckDeployment extends Command
{
    protected $signature = 'nfcheck:check-deployment {--json}';

    protected $description = 'Read-only configuration preflight; does not certify TLS, backups or campus readiness';

    public function handle(DeploymentChecks $review): int
    {
        $checks = $review->evaluate([
            'environment' => app()->environment(), 'debug' => config('app.debug'), 'key' => config('app.key'), 'app_url' => config('app.url'),
            'preview' => config('nfcheck.ui_preview'), 'simulator' => config('nfcheck.nfc_simulator'), 'hardware' => config('nfcheck.hardware_lab'),
            'session_driver' => config('session.driver'), 'session_encrypt' => config('session.encrypt'), 'session_http_only' => config('session.http_only'), 'session_secure' => config('session.secure'), 'same_site' => config('session.same_site'), 'session_minutes' => config('session.lifetime'),
            'disk' => config('filesystems.default'), 'local_serve' => config('filesystems.disks.local.serve'), 'public_storage_link' => file_exists(public_path('storage')) || is_link(public_path('storage')),
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
