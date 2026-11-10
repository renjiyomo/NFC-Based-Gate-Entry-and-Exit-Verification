<?php

namespace Tests\Unit;

use App\Support\CloudServices;
use PHPUnit\Framework\TestCase;

class CloudServicesTest extends TestCase
{
    private function candidate(): array
    {
        return ['tidb' => ['host' => 'synthetic.aws.tidbcloud.com', 'port' => 4000, 'username' => 'synthetic.root', 'password' => 'private-test-marker'], 'supabase' => ['endpoint' => 'https://synthetic.storage.supabase.co/storage/v1/s3', 'region' => 'ap-southeast-1', 'bucket' => 'nfcheck-photos', 'access_key_id' => 'private-test-marker', 'secret_access_key' => 'private-test-marker']];
    }

    public function test_unexpected_cloud_targets_are_rejected_without_echoing_credentials(): void
    {
        CloudServices::validate($this->candidate());
        foreach (['http://127.0.0.1/storage/v1/s3', 'https://synthetic.supabase.co.attacker.invalid/storage/v1/s3', 'https://private-test-marker@synthetic.supabase.co/storage/v1/s3'] as $endpoint) {
            $c = $this->candidate();
            $c['supabase']['endpoint'] = $endpoint;
            try {
                CloudServices::validate($c);
                $this->fail('An unexpected endpoint was accepted.');
            } catch (\RuntimeException $e) {
                $this->assertStringNotContainsString('private-test-marker', $e->getMessage());
            }
        }
        $c = $this->candidate();
        $c['tidb']['host'] = '127.0.0.1';
        $this->expectException(\RuntimeException::class);
        CloudServices::validate($c);
    }
}
