<?php

namespace Tests\Unit;

use App\Support\RecoveryArchive;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class RecoveryArchiveTest extends TestCase
{
    public function test_archive_encrypts_all_entries_round_trips_and_refuses_wrong_keys(): void
    {
        $input = tempnam(sys_get_temp_dir(), 'nfcheck-input-');
        $archive = $input.'.zip';
        $key = base64_encode(random_bytes(32));
        try {
            file_put_contents($input, 'Synthetic recovery marker');
            $service = new RecoveryArchive;
            $service->create($archive, ['database.sql' => $input], $key, ['synthetic' => true]);
            $this->assertStringNotContainsString('Synthetic recovery marker', file_get_contents($archive));
            $this->assertSame(['metadata' => ['synthetic' => true], 'files' => ['database.sql' => 'Synthetic recovery marker']], $service->read($archive, $key));
            try {
                $service->read($archive, base64_encode(random_bytes(32)));
                $this->fail('Wrong recovery key accepted.');
            } catch (RuntimeException $e) {
                $this->assertSame('Recovery decryption failed.', $e->getMessage());
            }
            $this->expectException(RuntimeException::class);
            $service->create($archive, ['database.sql' => $input], $key, []);
        } finally {
            @unlink($input);
            if (is_file($archive)) {
                unlink($archive);
            }
        }
    }

    public function test_unknown_or_traversing_entries_are_rejected_without_extraction(): void
    {
        $input = tempnam(sys_get_temp_dir(), 'nfcheck-input-');
        $archive = $input.'.zip';
        try {
            file_put_contents($input, 'Synthetic only');
            $this->expectException(RuntimeException::class);
            (new RecoveryArchive)->create($archive, ['../.env' => $input], base64_encode(random_bytes(32)), []);
        } finally {
            @unlink($input);
            if (is_file($archive)) {
                unlink($archive);
            }
        }
    }

    public function test_unencrypted_archive_cannot_be_used_for_recovery(): void
    {
        $archive = tempnam(sys_get_temp_dir(), 'nfcheck-archive-');
        try {
            $zip = new \ZipArchive;
            $zip->open($archive, \ZipArchive::OVERWRITE);
            $zip->addFromString('database.sql', 'Synthetic only');
            $zip->addFromString('manifest.json', '{}');
            $zip->close();
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Invalid or oversized recovery archive.');
            (new RecoveryArchive)->read($archive, base64_encode(random_bytes(32)));
        } finally {
            unlink($archive);
        }
    }

    public function test_changed_entry_with_stale_manifest_fails_integrity_verification(): void
    {
        $input = tempnam(sys_get_temp_dir(), 'nfcheck-input-');
        $archive = $input.'.zip';
        $key = base64_encode(random_bytes(32));
        try {
            file_put_contents($input, 'Synthetic original');
            $service = new RecoveryArchive;
            $service->create($archive, ['database.sql' => $input], $key, []);
            $zip = new \ZipArchive;
            $zip->open($archive);
            $zip->addFromString('database.sql', 'Synthetic changed');
            $zip->setEncryptionName('database.sql', \ZipArchive::EM_AES_256, $key);
            $zip->close();
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Recovery integrity check failed.');
            $service->read($archive, $key);
        } finally {
            unlink($input);
            if (is_file($archive)) {
                unlink($archive);
            }
        }
    }
}
