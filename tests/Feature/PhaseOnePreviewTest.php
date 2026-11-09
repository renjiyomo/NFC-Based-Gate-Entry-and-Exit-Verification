<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PhaseOnePreviewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config(['nfcheck.ui_preview' => true]);

        // Phase 1 shells must not establish an operational database connection.
        DB::shouldReceive('connection')->never();
    }

    public static function pages(): array
    {
        return array_map(fn (string $page): array => [$page], [
            'dashboard', 'students', 'employees', 'credentials', 'guards', 'gates',
            'scanner', 'history', 'reports', 'analytics', 'settings',
        ]);
    }

    #[DataProvider('pages')]
    public function test_local_pages_render_without_a_database(string $page): void
    {
        $this->get('/preview/'.$page)
            ->assertOk()
            ->assertInertia(fn (Assert $response) => $response
                ->component('Workspace')
                ->where('page', $page)
                ->where('app.preview', true)
                ->where('app.phase', 11)
                ->missing('auth')
                ->missing('users'));
    }

    public function test_login_is_an_explicit_preview_without_authentication(): void
    {
        $this->get('/preview/login')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Login')
            ->where('app.preview', true)
            ->missing('auth'));

        $this->post('/preview/login', ['email' => 'sample@example.invalid', 'password' => 'sample'])
            ->assertStatus(405);
    }

    public function test_inertia_navigation_returns_only_preview_props(): void
    {
        $version = app(HandleInertiaRequests::class)->version(Request::create('/'));

        $this->get('/preview/scanner', [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version ?? '',
        ])
            ->assertOk()
            ->assertHeader('X-Inertia', 'true')
            ->assertJsonPath('component', 'Workspace')
            ->assertJsonPath('props.page', 'scanner')
            ->assertJsonMissingPath('props.auth')
            ->assertJsonMissingPath('props.env');
    }

    public function test_preview_requires_the_explicit_flag(): void
    {
        config(['nfcheck.ui_preview' => false]);

        foreach (['/preview/login', '/preview/dashboard'] as $url) {
            $this->get($url)->assertNotFound();
            $this->get($url, ['X-Inertia' => 'true'])->assertNotFound();
        }
    }

    public function test_production_rejects_preview_even_if_flag_is_enabled(): void
    {
        $this->app->instance('env', 'production');

        foreach (['/preview/login', '/preview/dashboard'] as $url) {
            $this->get($url)->assertNotFound();
            $this->get($url, ['X-Inertia' => 'true'])->assertNotFound();
        }
    }

    public function test_non_loopback_client_cannot_open_preview(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
            ->get('/preview/dashboard', ['X-Forwarded-For' => '127.0.0.1'])
            ->assertNotFound();
    }

    public function test_unlisted_pages_and_operational_endpoints_are_unavailable(): void
    {
        $this->get('/preview/not-a-module')->assertNotFound();
        $this->get('/admin')->assertNotFound();
        $this->post('/preview/scanner')->assertStatus(405);
        $this->post('/api/verify', ['credential' => 'not-a-real-token'])->assertNotFound();
    }

    public function test_preview_sets_privacy_and_frame_headers(): void
    {
        $this->get('/preview/dashboard')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }

    public function test_root_and_health_check_work_without_database_setup(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/up')->assertOk();
        $this->assertFileDoesNotExist(database_path('database.sqlite'));
    }
}
