<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\DatabaseTestCase;

class PasswordRecoveryTest extends DatabaseTestCase
{
    public function test_recovery_redirects_stay_on_the_form_without_a_referer_after_ajax_navigation(): void
    {
        $this->get('/login')->assertOk();
        $headers = ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'text/html'];
        $this->get('/forgot-password', $headers)->assertOk();
        $this->post('/forgot-password', ['email' => 'unknown@example.invalid'], $headers)->assertRedirect('/forgot-password')->assertSessionHas('status');
        $this->post('/forgot-password', ['email' => 'invalid'], $headers)->assertRedirect('/forgot-password')->assertSessionHasErrors('email');
        $token = str_repeat('a', 64);
        $this->post('/reset-password', ['email' => 'unknown@example.invalid', 'token' => $token, 'password' => 'short'], $headers)
            ->assertRedirect(config('app.url').'/reset-password/'.$token.'?email=unknown%40example.invalid')->assertSessionHasErrors('password');
    }

    public function test_recovery_is_generic_for_existing_unknown_and_inactive_accounts(): void
    {
        Notification::fake();
        $active = $this->user();
        $inactive = $this->user(['status' => 'inactive']);
        $message = 'If this account is eligible, password reset instructions will arrive shortly.';
        foreach ([$active->email, $inactive->email, 'unknown@example.invalid'] as $email) {
            $this->from('/forgot-password')->post('/forgot-password', compact('email'))->assertRedirect('/forgot-password')->assertSessionHas('status', $message);
        }
        Notification::assertSentTo($active, ResetPassword::class);
        Notification::assertNotSentTo($inactive, ResetPassword::class);
        Notification::assertCount(1);
        $this->assertStringNotContainsString($active->email, AuditLog::all()->toJson());
    }

    public function test_link_uses_the_configured_origin_and_tokens_are_hashed(): void
    {
        Notification::fake();
        $user = $this->user();
        $this->withHeader('Host', 'attacker.example')->post('/forgot-password', ['email' => $user->email])->assertSessionHasNoErrors();
        $notification = Notification::sent($user, ResetPassword::class)->first();
        $stored = DB::table('password_reset_tokens')->where('email', $user->email)->value('token');
        $this->assertNotSame($notification->token, $stored);
        $this->assertTrue(Hash::check($notification->token, $stored));
        $mail = $notification->toMail($user);
        $this->assertStringStartsWith(config('app.url').'/reset-password/', $mail->actionUrl);
        $this->assertStringNotContainsString('attacker.example', $mail->actionUrl);
    }

    public function test_password_reset_is_single_use_revokes_sessions_and_does_not_auto_login(): void
    {
        $user = $this->user(['must_change_password' => true]);
        $token = Password::createToken($user);
        DB::table('sessions')->insert(['id' => 'reset-other-session', 'user_id' => $user->id, 'payload' => 'opaque', 'last_activity' => time()]);
        $new = 'silver window garden breeze 728';
        $payload = ['email' => $user->email, 'token' => $token, 'password' => $new, 'password_confirmation' => $new];
        $this->post('/reset-password', $payload)->assertRedirect('/login')->assertSessionHasNoErrors();
        $this->assertGuest();
        $this->assertTrue(Hash::check($new, $user->fresh()->password));
        $this->assertFalse($user->fresh()->must_change_password);
        $this->assertSame(2, $user->fresh()->auth_version);
        $this->assertDatabaseMissing('sessions', ['id' => 'reset-other-session']);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->post('/reset-password', $payload)->assertSessionHasErrors('email');
        $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => $user->email, 'password' => $new])->assertRedirect('/admin/dashboard');
        $this->assertSame(1, AuditLog::where('action', 'password.reset_completed')->count());
        $this->assertStringNotContainsString($token, AuditLog::all()->toJson());
        $this->assertStringNotContainsString($new, AuditLog::all()->toJson());
    }

    public function test_expired_invalid_and_inactive_reset_links_are_rejected(): void
    {
        $user = $this->user();
        $token = Password::createToken($user);
        $payload = ['email' => $user->email, 'token' => $token, 'password' => 'silver window garden breeze 728', 'password_confirmation' => 'silver window garden breeze 728'];
        $this->post('/reset-password', [...$payload, 'token' => 'invalid'])->assertSessionHasErrors('email');
        DB::table('password_reset_tokens')->where('email', $user->email)->update(['created_at' => now()->subMinutes(31)]);
        $this->post('/reset-password', $payload)->assertSessionHasErrors('email');
        DB::table('password_reset_tokens')->where('email', $user->email)->update(['created_at' => now()]);
        $user->update(['status' => 'inactive']);
        $this->post('/reset-password', $payload)->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check(self::PASSWORD, $user->fresh()->password));
    }

    public function test_reset_tokens_and_passwords_are_not_flashed_on_validation_failure(): void
    {
        $this->post('/reset-password', ['email' => 'user@example.invalid', 'token' => 'secret-token-value', 'password' => 'short', 'password_confirmation' => 'different'])
            ->assertSessionHasErrors('password')->assertSessionMissing('_old_input.token')->assertSessionMissing('_old_input.password')->assertSessionMissing('_old_input.password_confirmation');
    }

    public function test_recovery_is_throttled_and_a_second_request_does_not_rotate_a_recent_token(): void
    {
        Notification::fake();
        $user = $this->user();
        for ($i = 0; $i < 3; $i++) {
            $this->post('/forgot-password', ['email' => $user->email])->assertSessionHasNoErrors();
        }
        Notification::assertSentToTimes($user, ResetPassword::class, 1);
        $this->post('/forgot-password', ['email' => $user->email])->assertStatus(429);
    }
}
