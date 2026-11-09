<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();
        $response = $next($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), nfc=(self)');
        // The optional Vite dev origin must remain loopback, never a client input.
        $dev = '';
        $socket = '';
        if (app()->environment('local') && Vite::isRunningHot()) {
            $hot = trim(file_get_contents(Vite::hotFile()));
            if (preg_match('#^http://(127\.0\.0\.1|localhost):5173$#D', $hot)) {
                $dev = ' '.$hot;
                $socket = ' '.str_replace('http:', 'ws:', $hot);
            }
        }
        $response->headers->set('Content-Security-Policy', "default-src 'none'; script-src 'self' 'nonce-{$nonce}'{$dev}; script-src-attr 'none'; style-src 'self' 'unsafe-inline'{$dev}; connect-src 'self'{$dev}{$socket}; img-src 'self' data: blob:; font-src 'self' data:; media-src 'none'; worker-src 'none'; frame-src 'none'; frame-ancestors 'none'; form-action 'self'; base-uri 'self'; object-src 'none'");
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
