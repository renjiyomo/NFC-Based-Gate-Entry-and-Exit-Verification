<?php

namespace Tests\Feature;

use App\Enums\RecordStatus;
use App\Enums\UserRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\AuditLog;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\DatabaseTestCase;

class AuthenticationTest extends DatabaseTestCase
{
    public function test_login_is_real_and_workspace_requires_authentication(): void
    {
        $this->get('/login')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Login')->where('preview', false)->where('auth.user', null)->where('app.preview', false));
        $this->get('/admin/dashboard')->assertRedirect('/login');
        $this->get('/guard/scanner')->assertRedirect('/login');
        $this->post('/register', ['role' => 'admin'])->assertNotFound();
    }

    public function test_login_normalizes_email_rotates_session_and_logs_out(): void
    {
        $user = $this->user(['email' => 'operator@example.invalid']);
        $this->get('/login');
        $before = session()->getId();
        $csrf = session()->token();
        $this->post('/login', ['email' => ' OPERATOR@example.invalid ', 'password' => self::PASSWORD, 'role' => 'guard'])
            ->assertRedirect('/admin/dashboard')->assertSessionHas('auth_version', 1);
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($before, session()->getId());
        $this->assertNotSame($csrf, session()->token());
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->get('/admin/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page->where('auth.user.email', $user->email)->where('auth.user.role', 'admin')->missing('auth.user.password'));
        $session = session()->getId();
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
        $this->assertNotSame($session, session()->getId());
        $version = app(HandleInertiaRequests::class)->version(Request::create('/'));
        $this->get('/login', ['X-Inertia' => 'true', 'X-Inertia-Version' => $version ?? ''])->assertJsonPath('encryptHistory', true)->assertJsonPath('clearHistory', true);
        $this->get('/admin/dashboard')->assertRedirect('/login');
        $this->assertSame(1, AuditLog::where('action', 'login.succeeded')->count());
        $this->assertSame(1, AuditLog::where('action', 'logout')->count());
    }

    public function test_invalid_unknown_and_inactive_accounts_share_a_generic_error(): void
    {
        $user = $this->user();
        foreach ([[$user->email, 'incorrect password'], ['unknown@example.invalid', self::PASSWORD]] as [$email, $password]) {
            $this->post('/login', compact('email', 'password'))->assertSessionHasErrors(['email' => 'These credentials could not be verified.']);
            $this->assertGuest();
        }
        $user->update(['status' => RecordStatus::Inactive]);
        $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD])->assertSessionHasErrors(['email' => 'These credentials could not be verified.']);
        $this->assertGuest();
        $serialized = AuditLog::all()->toJson();
        foreach ([$user->email, self::PASSWORD, 'incorrect password', '127.0.0.1'] as $sensitive) {
            $this->assertStringNotContainsString($sensitive, $serialized);
        }
    }

    public function test_repeated_failures_are_throttled_without_disclosing_accounts(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'unknown@example.invalid', 'password' => self::PASSWORD])->assertSessionHasErrors('email');
        }
        $this->post('/login', ['email' => 'unknown@example.invalid', 'password' => self::PASSWORD])
            ->assertSessionHasErrors(['email' => 'Too many sign-in attempts. Wait a minute and try again.']);
        $this->assertSame(1, AuditLog::where('action', 'login.throttled')->count());
    }

    public function test_initial_password_change_is_required_and_preserves_only_current_session(): void
    {
        $user = $this->user(['must_change_password' => true]);
        $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD])->assertRedirect('/account/password');
        $this->get('/admin/dashboard')->assertRedirect('/account/password');
        $this->get('/account/password')->assertOk();
        DB::table('sessions')->insert(['id' => 'other-session', 'user_id' => $user->id, 'payload' => 'opaque', 'last_activity' => time()]);
        $new = 'morning wind silver forest 923';
        $this->put('/account/password', ['current_password' => self::PASSWORD, 'password' => $new, 'password_confirmation' => $new])->assertRedirect('/admin/dashboard');
        $this->assertTrue(Hash::check($new, $user->fresh()->password));
        $this->assertFalse($user->fresh()->must_change_password);
        $this->assertSame(2, $user->fresh()->auth_version);
        $this->assertDatabaseMissing('sessions', ['id' => 'other-session']);
        $this->get('/admin/dashboard')->assertOk();
    }

    public function test_password_change_validates_current_password_and_strength(): void
    {
        $user = $this->user();
        $this->signIn($user);
        $this->put('/account/password', ['current_password' => 'wrong', 'password' => 'morning wind silver forest 923', 'password_confirmation' => 'morning wind silver forest 923'])->assertSessionHasErrors('current_password');
        foreach (['short', str_repeat('a', 15), 'passwordpassword', str_repeat('x', 129)] as $password) {
            $this->put('/account/password', ['current_password' => self::PASSWORD, 'password' => $password, 'password_confirmation' => $password])->assertSessionHasErrors('password');
        }
        $this->assertTrue(Hash::check(self::PASSWORD, $user->fresh()->password));
    }

    public function test_account_version_or_disabled_status_revokes_session(): void
    {
        $user = $this->user();
        $this->signIn($user);
        $user->update(['role' => UserRole::Guard]);
        $this->get('/admin/dashboard')->assertRedirect('/login');
        $this->assertGuest();
        $user->refresh();
        $this->signIn($user);
        $user->update(['status' => RecordStatus::Inactive]);
        $this->get('/guard/scanner')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_failed_audit_rolls_back_login_and_leaves_no_authenticated_session(): void
    {
        $user = $this->user();
        $audit = \Mockery::mock(AuditLogger::class)->makePartial();
        $audit->shouldReceive('record')->once()->andThrow(new \RuntimeException('audit unavailable'));
        $this->app->instance(AuditLogger::class, $audit);
        $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD])->assertStatus(500);
        $this->assertGuest();
        $this->assertNull($user->fresh()->last_login_at);
    }

    public function test_csrf_is_required_outside_the_test_bypass(): void
    {
        $this->app->instance('env', 'local');
        $this->post('/login', ['email' => 'test@example.invalid', 'password' => self::PASSWORD])->assertStatus(419);
        $this->post('/logout')->assertStatus(419);
    }

    public function test_a_fresh_request_reloads_account_state_and_rejects_a_revoked_session(): void
    {
        $user = $this->user();
        $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD])->assertRedirect('/admin/dashboard');
        $user->update(['status' => RecordStatus::Inactive]);
        app('auth')->forgetGuards();
        $this->get('/admin/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_session_cookie_is_http_only_lax_and_private(): void
    {
        $response = $this->get('/login')->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Referrer-Policy', 'no-referrer');
        $cookie = collect($response->headers->getCookies())->first(fn ($cookie) => $cookie->getName() === config('session.cookie'));
        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
        $this->assertTrue(config('session.encrypt'));
        $this->assertSame(30, config('session.lifetime'));
    }
}
