<?php

namespace Tests\Unit;

use App\Support\DevelopmentRequestLog;
use PHPUnit\Framework\TestCase;

class DevelopmentRequestLogTest extends TestCase
{
    public function test_arbitrary_paths_queries_reset_links_and_control_characters_are_not_logged(): void
    {
        $secret = 'nfcheck:v1:'.str_repeat('Z', 43);
        foreach (['/reset-password/'.$secret, '/'.$secret, '/admin/'.$secret, '/admin/credentials/'.$secret.'/activate', '/admin/history?q='.$secret, '/login?token='.$secret, '/bogus%0a'.$secret] as $uri) {
            $safe = DevelopmentRequestLog::path($uri);
            $this->assertStringNotContainsString($secret, $safe);
            $this->assertStringNotContainsString("\n", $safe);
            $this->assertStringNotContainsString('?', $safe);
        }
        $this->assertSame('/admin/history', DevelopmentRequestLog::path('/admin/history?q=person'));
        $this->assertSame('/reset-password/[redacted]', DevelopmentRequestLog::path('/reset-password/anything'));
        $this->assertSame('OTHER', DevelopmentRequestLog::method("GET\nsecret"));
    }
}
