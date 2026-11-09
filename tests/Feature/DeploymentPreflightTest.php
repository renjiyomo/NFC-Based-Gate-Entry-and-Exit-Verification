<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\DatabaseTestCase;

class DeploymentPreflightTest extends DatabaseTestCase
{
    public function test_local_preflight_reports_boolean_findings_without_secrets_queries_or_external_requests(): void
    {
        Http::preventStrayRequests();
        $key = 'base64:'.base64_encode(str_repeat('x', 32));
        config(['app.key' => $key]);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->assertSame(1, Artisan::call('nfcheck:check-deployment', ['--json' => true]));
        $output = Artisan::output();
        $result = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        $this->assertFalse($result['configuration_passed']);
        $this->assertFalse($result['checks']['production_environment']);
        $this->assertStringNotContainsString($key, $output);
        $this->assertSame([], DB::getQueryLog());
        DB::disableQueryLog();
        Http::assertNothingSent();
    }
}
