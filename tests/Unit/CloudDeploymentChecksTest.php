<?php

namespace Tests\Unit;

use App\Support\DeploymentChecks;
use PHPUnit\Framework\TestCase;

class CloudDeploymentChecksTest extends TestCase
{
    private function candidate(): array
    {
        return ['environment' => 'production', 'debug' => false, 'key' => 'base64:'.base64_encode(str_repeat('x', 32)), 'app_url' => 'https://nfcheck.vercel.app', 'preview' => false, 'simulator' => false, 'hardware' => false, 'session_driver' => 'database', 'session_encrypt' => true, 'session_http_only' => true, 'session_secure' => true, 'same_site' => 'lax', 'session_minutes' => 30, 'disk' => 'local', 'photo_disk' => 's3', 'local_serve' => false, 'public_storage_link' => false, 'log_channel' => 'stderr', 'log_level' => 'warning', 'log_days' => 14, 'ai_enabled' => false, 'private_ai_file' => false, 'built_assets' => true, 'hot_file' => false, 'cloud' => true, 's3_endpoint' => 'https://synthetic.storage.supabase.co/storage/v1/s3', 's3_visibility' => 'private', 's3_throw' => true, 's3_credentials' => true, 's3_path_style' => true, 's3_bucket' => 'nfcheck-photos', 's3_region' => 'ap-southeast-1', 'cache_store' => 'database', 'db_ca_readable' => true, 'db_verify_cert' => true, 'db_url_unset' => true, 'db_driver' => 'pgsql', 'db_schema' => 'nfcheck_online', 'db_user' => 'nfcheck_app.synthetic', 'trusted_proxies' => ['*'], 'password_recovery' => false];
    }

    public function test_cloud_preflight_requires_private_storage_tls_shared_cache_and_restricted_identity(): void
    {
        $review = new DeploymentChecks;
        $this->assertNotContains(false, $review->evaluate($this->candidate()));
        foreach (['http://synthetic.supabase.co/storage/v1/s3', 'https://synthetic.supabase.co.attacker.invalid/storage/v1/s3', 'https://user@synthetic.supabase.co/storage/v1/s3', 'https://synthetic.supabase.co/storage/v1/s3?key=x', 'https://synthetic.supabase.co:8443/storage/v1/s3'] as $endpoint) {
            $this->assertFalse($review->evaluate([...$this->candidate(), 's3_endpoint' => $endpoint])['private_photo_storage']);
        }
        $bad = $review->evaluate([...$this->candidate(), 'cache_store' => 'file', 'db_verify_cert' => false, 'db_user' => 'postgres.synthetic', 'trusted_proxies' => [], 'password_recovery' => true]);
        foreach (['shared_cache', 'verified_database_tls', 'restricted_database_account', 'explicit_proxy_configuration', 'email_recovery_disabled'] as $check) {
            $this->assertFalse($bad[$check]);
        }
    }
}
