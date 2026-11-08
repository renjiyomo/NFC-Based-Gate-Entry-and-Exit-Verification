<?php

namespace App\Providers;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        ini_set('zend.exception_ignore_args', '1');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('admin.workspace', fn (User $user): bool => $user->isAdmin());
        Gate::define('guard.workspace', fn (User $user): bool => $user->isGuard());
        Gate::after(function (User $user, string $ability, ?bool $result, array $arguments): void {
            if ($result === false) {
                $target = ($arguments[0] ?? null) instanceof Model ? $arguments[0] : null;
                app(AuditLogger::class)->record('authorization.denied', $user, $target, ['reason' => $ability], 'denied');
            }
        });
        RateLimiter::for('login-ingress', fn (Request $request) => Limit::perMinute(60)->by(app(AuditLogger::class)->fingerprint((string) $request->ip())));
        RateLimiter::for('recovery', function (Request $request): array {
            $audit = app(AuditLogger::class);
            $email = $request->input('email');

            return [Limit::perMinute(5)->by('recovery-ip:'.$audit->fingerprint((string) $request->ip())),
                Limit::perMinute(3)->by('recovery-subject:'.$audit->fingerprint(is_string($email) ? mb_strtolower(trim($email)) : ''))];
        });
        RateLimiter::for('reset', fn (Request $request) => Limit::perMinute(10)->by(app(AuditLogger::class)->fingerprint((string) $request->ip())));
        RateLimiter::for('password-change', fn (Request $request) => Limit::perMinute(5)->by(config('database.connections.mysql.database').':'.$request->user()->id));
        RateLimiter::for('management', fn (Request $request) => Limit::perMinute(60)->by(config('database.connections.mysql.database').':'.$request->user()->id));
        RateLimiter::for('credential-write', fn (Request $request) => Limit::perMinute(30)->by(config('database.connections.mysql.database').':'.$request->user()->id));
        RateLimiter::for('credential-check', fn (Request $request) => Limit::perMinute(60)->by(config('database.connections.mysql.database').':'.$request->user()->id));
        RateLimiter::for('gate-write', fn (Request $request) => Limit::perMinute(120)->by(config('database.connections.mysql.database').':'.$request->user()->id));
        RateLimiter::for('gate-read', fn (Request $request) => Limit::perMinute(180)->by(config('database.connections.mysql.database').':'.$request->user()->id));
        RateLimiter::for('guard-setup', fn (Request $request) => Limit::perMinute(5)->by(config('database.connections.mysql.database').':'.$request->user()->id));
        ResetPassword::createUrlUsing(fn (User $user, string $token): string => rtrim(config('app.url'), '/').'/reset-password/'.rawurlencode($token).'?email='.rawurlencode($user->email));
    }
}
