<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class CloudAssetUrlsTest extends TestCase
{
    private function configure(string $environment, bool $cloud, string $origin): void
    {
        $this->app->instance('env', $environment);
        config(['cloud.enabled' => $cloud, 'app.url' => $origin]);
        URL::setRequest(Request::create('http://nfcheck.test/login'));
        URL::forceScheme(null);
        (new AppServiceProvider($this->app))->boot();
    }

    public function test_cloud_uses_https_asset_and_route_urls_behind_an_http_container_hop(): void
    {
        $this->configure('production', true, 'https://nfcheck.test');
        $this->assertSame('https://nfcheck.test/build/app.js', asset('build/app.js'));
        $this->assertSame('https://nfcheck.test/login', route('login'));
        $response = $this->get('http://nfcheck.test/login')->assertOk();
        $this->assertMatchesRegularExpression('#https://nfcheck\.test/build/assets/app-[^"\s]+\.js#', $response->getContent());
        $this->assertStringNotContainsString('http://nfcheck.test/build/', $response->getContent());
    }

    public function test_local_http_urls_remain_available(): void
    {
        $this->configure('local', false, 'http://nfcheck.test');
        $this->assertSame('http://nfcheck.test/build/app.js', asset('build/app.js'));
        $this->assertSame('http://nfcheck.test/login', route('login'));
    }
}
