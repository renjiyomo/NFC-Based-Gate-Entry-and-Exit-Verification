<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class StorageEnvironmentTest extends TestCase
{
    private function credentials(array $environment): array
    {
        $process = new Process([PHP_BINARY, '-r', <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$config = require 'config/filesystems.php';
echo json_encode([$config['disks']['s3']['key'], $config['disks']['s3']['secret']]);
PHP], dirname(__DIR__, 2), array_replace([
            'NFCHECK_S3_ACCESS_KEY_ID' => false,
            'NFCHECK_S3_SECRET_ACCESS_KEY' => false,
            'AWS_ACCESS_KEY_ID' => false,
            'AWS_SECRET_ACCESS_KEY' => false,
        ], $environment));
        $process->mustRun();

        return json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_project_storage_keys_take_precedence_over_platform_aws_values(): void
    {
        $this->assertSame(['project-key', 'project-secret'], $this->credentials([
            'NFCHECK_S3_ACCESS_KEY_ID' => 'project-key',
            'NFCHECK_S3_SECRET_ACCESS_KEY' => 'project-secret',
            'AWS_ACCESS_KEY_ID' => 'platform-key',
            'AWS_SECRET_ACCESS_KEY' => 'platform-secret',
        ]));
    }

    public function test_existing_aws_configuration_is_preserved(): void
    {
        $this->assertSame(['legacy-key', 'legacy-secret'], $this->credentials([
            'AWS_ACCESS_KEY_ID' => 'legacy-key', 'AWS_SECRET_ACCESS_KEY' => 'legacy-secret',
        ]));
    }

    public function test_missing_credentials_remain_missing(): void
    {
        $this->assertSame([null, null], $this->credentials([]));
    }

    public function test_explicitly_empty_project_credentials_do_not_use_platform_credentials(): void
    {
        $this->assertSame(['', ''], $this->credentials([
            'NFCHECK_S3_ACCESS_KEY_ID' => '', 'NFCHECK_S3_SECRET_ACCESS_KEY' => '',
            'AWS_ACCESS_KEY_ID' => 'platform-key', 'AWS_SECRET_ACCESS_KEY' => 'platform-secret',
        ]));
    }
}
