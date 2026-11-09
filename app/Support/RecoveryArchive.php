<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

final class RecoveryArchive
{
    private const LIMIT = 67108864;

    public function create(string $destination, array $files, string $key, array $metadata): void
    {
        if (strlen($key) < 40 || file_exists($destination) || ! $files) {
            throw new RuntimeException('Invalid recovery archive destination, key or inputs.');
        }
        $zip = new ZipArchive;
        if ($zip->open($destination, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
            throw new RuntimeException('Cannot create recovery archive.');
        }
        try {
            $hashes = [];
            $total = 0;
            foreach ($files as $name => $path) {
                $this->name($name);
                if (! is_file($path) || is_link($path) || ($total += filesize($path)) > self::LIMIT) {
                    throw new RuntimeException('Invalid or oversized recovery input.');
                }
                $hashes[$name] = hash_file('sha256', $path);
                if (! $zip->addFile($path, $name) || ! $zip->setEncryptionName($name, ZipArchive::EM_AES_256, $key)) {
                    throw new RuntimeException('Cannot encrypt recovery entry.');
                }
            }
            $manifest = json_encode(['format' => 1, 'metadata' => $metadata, 'files' => $hashes], JSON_THROW_ON_ERROR);
            if (! $zip->addFromString('manifest.json', $manifest) || ! $zip->setEncryptionName('manifest.json', ZipArchive::EM_AES_256, $key)) {
                throw new RuntimeException('Cannot encrypt recovery manifest.');
            }
        } catch (\Throwable $error) {
            $zip->close();
            if (is_file($destination)) {
                unlink($destination);
            }
            throw $error;
        }
        if (! $zip->close()) {
            if (is_file($destination)) {
                unlink($destination);
            }
            throw new RuntimeException('Cannot finalize recovery archive.');
        }
    }

    /** Verify/decrypt in memory; never extract arbitrary archive paths. */
    public function read(string $archive, string $key): array
    {
        $zip = new ZipArchive;
        if ($zip->open($archive, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('Cannot open recovery archive.');
        }
        try {
            $zip->setPassword($key);
            $data = [];
            $total = 0;
            if ($zip->numFiles < 2 || $zip->numFiles > 10002) {
                throw new RuntimeException('Unexpected recovery entry count.');
            }
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->statIndex($i);
                $name = $entry['name'];
                if ($name !== 'manifest.json') {
                    $this->name($name);
                }
                if (isset($data[$name]) || $entry['encryption_method'] !== ZipArchive::EM_AES_256 || ($total += $entry['size']) > self::LIMIT) {
                    throw new RuntimeException('Invalid or oversized recovery archive.');
                }
                $bytes = @$zip->getFromIndex($i);
                if ($bytes === false || strlen($bytes) !== $entry['size']) {
                    throw new RuntimeException('Recovery decryption failed.');
                }
                $data[$name] = $bytes;
            }
            $manifest = json_decode($data['manifest.json'] ?? '', true, 512, JSON_THROW_ON_ERROR);
            unset($data['manifest.json']);
            if (($manifest['format'] ?? null) !== 1 || ! is_array($manifest['files'] ?? null) || count($manifest['files']) !== count($data)) {
                throw new RuntimeException('Invalid recovery manifest.');
            }
            foreach ($data as $name => $bytes) {
                if (! isset($manifest['files'][$name]) || ! hash_equals($manifest['files'][$name], hash('sha256', $bytes))) {
                    throw new RuntimeException('Recovery integrity check failed.');
                }
            }

            return ['metadata' => $manifest['metadata'], 'files' => $data];
        } finally {
            $zip->close();
        }
    }

    private function name(string $name): void
    {
        if (! in_array($name, ['database.sql', 'environment.env'], true) && ! preg_match('#^private/person-photos/[a-f0-9-]{36}\.jpg$#D', $name)) {
            throw new RuntimeException('Unexpected recovery entry name.');
        }
    }
}
