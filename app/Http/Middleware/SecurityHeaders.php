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
        Vite::useCspNonce();
        $response = $next($request);
        $admin = $request->is('admin', 'admin/*', 'livewire/*', 'livewire-*/*');
        $script = $admin ? "'self' 'unsafe-inline' 'unsafe-eval'" : "'self' 'nonce-".Vite::cspNonce()."'";
        $connect = "'self'";
        if (app()->environment('local') && Vite::isRunningHot()) {
            $url = trim(file_get_contents(Vite::hotFile()));
            $parts = parse_url($url);
            if (isset($parts['host'], $parts['scheme']) && in_array($parts['scheme'], ['http', 'https'], true)) {
                $origin = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
                $script .= ' '.$origin;
                $connect .= ' '.$origin.' '.($parts['scheme'] === 'https' ? 'wss' : 'ws').'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
            }
        }
        $response->headers->set('Content-Security-Policy', "default-src 'self'; script-src $script; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self' data:; connect-src $connect; object-src 'none'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'");
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        if (! $response->headers->has('Referrer-Policy')) {
            $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        }
        if ($admin || $request->is('panier', 'panier/*', 'commander', 'commandes/*')) {
            $response->headers->set('Cache-Control', 'private, no-store');
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
            $response->headers->set('Referrer-Policy', 'no-referrer');
        } elseif (! config('seo.indexable') && ! $response->headers->has('X-Robots-Tag')) {
            $response->headers->set('X-Robots-Tag', 'noindex, follow');
        }
        if ($request->isSecure() && app()->environment('production')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        return $response;
    }
}
