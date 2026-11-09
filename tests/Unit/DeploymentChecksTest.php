<?php

namespace Tests\Unit;

use App\Support\DeploymentChecks;
use PHPUnit\Framework\TestCase;

class DeploymentChecksTest extends TestCase
{
    private function candidate(): array
    {
        return ['environment' => 'production', 'debug' => false, 'key' => 'base64:'.base64_encode(random_bytes(32)), 'app_url' => 'https://gate.campus.example', 'preview' => false, 'simulator' => false, 'hardware' => false, 'session_driver' => 'database', 'session_encrypt' => true, 'session_http_only' => true, 'session_secure' => true, 'same_site' => 'lax', 'session_minutes' => 30, 'disk' => 'local', 'local_serve' => false, 'public_storage_link' => false, 'log_channel' => 'daily', 'log_level' => 'warning', 'log_days' => 14, 'ai_enabled' => false, 'private_ai_file' => false, 'built_assets' => true, 'hot_file' => false];
    }

    public function test_configuration_preflight_accepts_only_explicit_production_controls(): void
    {
        $review = new DeploymentChecks;
        $this->assertNotContains(false, $review->evaluate($this->candidate()));
        $bad = $review->evaluate([...$this->candidate(), 'environment' => 'local', 'debug' => true, 'key' => '', 'app_url' => 'http://localhost:8000', 'preview' => true, 'session_secure' => false, 'local_serve' => true, 'log_channel' => 'single', 'ai_enabled' => true, 'hot_file' => true]);
        $this->assertSame(10, count(array_filter($bad, fn ($v) => ! $v)));
    }

    public function test_placeholder_origins_and_private_lab_key_files_require_review(): void
    {
        $review = new DeploymentChecks;
        foreach (['https://nfcheck.example.invalid', 'http://campus.example', 'https://user:password@campus.example', 'https://user@campus.example', 'https://campus.example?token=x'] as $url) {
            $this->assertFalse($review->evaluate([...$this->candidate(), 'app_url' => $url])['https_origin']);
        }
        $this->assertFalse($review->evaluate([...$this->candidate(), 'private_ai_file' => true])['live_ai_disabled']);
    }
}
