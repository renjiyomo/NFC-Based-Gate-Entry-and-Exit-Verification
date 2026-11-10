<?php

namespace Tests\Unit;

use App\Casts\BinaryDigest;
use App\Database\PostgresConnector;
use App\Support\PostgresRecovery;
use App\Support\PostgresSchema;
use PHPUnit\Framework\TestCase;

class PostgresPortabilityTest extends TestCase
{
    public function test_binary_digest_reading_preserves_null_bytes_and_stream_positions(): void
    {
        $bytes = implode('', array_map('chr', range(0, 31)));
        $this->assertTrue(hash_equals($bytes, BinaryDigest::bytes('\\x'.bin2hex($bytes))));
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, $bytes);
        fseek($stream, 7);
        $this->assertTrue(hash_equals($bytes, BinaryDigest::bytes($stream)));
        $this->assertSame(7, ftell($stream));
        fclose($stream);
    }

    public function test_certificate_paths_with_spaces_are_quoted_for_libpq(): void
    {
        $method = new \ReflectionMethod(PostgresConnector::class, 'addSslOptions');
        $dsn = $method->invoke(new PostgresConnector, 'pgsql:host=example.invalid', ['sslmode' => 'verify-full', 'sslrootcert' => 'C:/private folder/database-ca.crt']);
        $this->assertStringContainsString("sslmode='verify-full'", $dsn);
        $this->assertStringContainsString("sslrootcert='C:/private folder/database-ca.crt'", $dsn);
        $this->assertStringContainsString("connect_timeout='10'", $dsn);
        $this->assertStringContainsString("tcp_user_timeout='30000'", $dsn);
    }

    public function test_restore_remapping_changes_schema_metadata_without_changing_copy_data(): void
    {
        $source = 'nfcheck_probe_1111111111111111';
        $target = 'nfcheck_probe_2222222222222222';
        $sql = 'CREATE SCHEMA "'.$source.'";'."\n".'COPY "'.$source.'"."sample" ("note") FROM stdin;'."\n".'The text "'.$source.'" must stay unchanged.'."\n\\.\n".'ALTER TABLE "'.$source.'"."sample" ADD CHECK (true);';
        $mapped = PostgresRecovery::remapDump($sql, $source, $target);
        $this->assertStringContainsString('CREATE SCHEMA "'.$target.'";', $mapped);
        $this->assertStringContainsString('COPY "'.$target.'"."sample"', $mapped);
        $this->assertStringContainsString('The text "'.$source.'" must stay unchanged.', $mapped);
        $this->assertStringContainsString('ALTER TABLE "'.$target.'"."sample"', $mapped);
    }

    public function test_schema_identifiers_reject_sql_fragments(): void
    {
        $this->expectException(\RuntimeException::class);
        PostgresSchema::identifier('nfcheck;drop schema public');
    }
}
