<?php

namespace Tests\Unit;

use App\Services\Summaries\ConfiguredProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SummaryWorkspaceTest extends TestCase
{
    public function test_original_workspace_requires_explicit_aggregate_approval(): void
    {
        Http::preventStrayRequests();
        config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'nfcheck', 'ai_summaries.enabled' => true, 'ai_summaries.allow_aggregate_workspaces' => false, 'ai_summaries.free_tier_confirmed' => true, 'ai_summaries.project_id' => 'synthetic-project', 'ai_summaries.api_key' => str_repeat('synthetic-', 4)]);
        $provider = app(ConfiguredProvider::class);
        $this->assertSame('workspace_not_approved', $provider->configuration()['reason']);
        config(['ai_summaries.allow_aggregate_workspaces' => true]);
        $this->assertTrue($provider->configuration()['ready']);
        $this->assertSame('anonymous_aggregates', $provider->configuration()['scope']);
        config(['database.connections.mysql.database' => 'unapproved_database']);
        $this->assertSame('workspace_not_approved', $provider->configuration()['reason']);
        Http::assertNothingSent();
    }

    public function test_online_approval_is_limited_to_the_cloud_postgresql_schema(): void
    {
        Http::preventStrayRequests();
        $this->app->instance('env', 'production');
        config(['cloud.enabled' => true, 'database.default' => 'pgsql', 'database.connections.pgsql.search_path' => 'nfcheck_online', 'ai_summaries.enabled' => true, 'ai_summaries.allow_aggregate_workspaces' => true, 'ai_summaries.free_tier_confirmed' => true, 'ai_summaries.project_id' => 'synthetic-project', 'ai_summaries.api_key' => str_repeat('synthetic-', 4)]);
        $provider = app(ConfiguredProvider::class);
        $this->assertTrue($provider->configuration()['ready']);
        $this->assertSame('anonymous_aggregates', $provider->configuration()['scope']);
        config(['cloud.enabled' => false]);
        $this->assertSame('workspace_not_approved', $provider->configuration()['reason']);
        config(['cloud.enabled' => true, 'database.connections.pgsql.search_path' => 'public']);
        $this->assertSame('workspace_not_approved', $provider->configuration()['reason']);
        Http::assertNothingSent();
    }
}
