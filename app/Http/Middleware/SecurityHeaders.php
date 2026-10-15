<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Global security headers for every response (web + API).
 *
 * CSP notes:
 *  - script-src is strict (no unsafe-inline / unsafe-eval). All inline event
 *    handlers were moved to addEventListener and runtime config is delivered
 *    via <meta> tags, so no script nonce is needed.
 *  - style-src keeps 'unsafe-inline' only for style="" attributes; scripts
 *    are the dangerous sink and are fully locked down.
 *  - CDN hosts are pinned with SRI hashes in the blade template.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' https://unpkg.com https://cdn.jsdelivr.net https://js.pusher.com",
            "style-src 'self' 'unsafe-inline' https://unpkg.com",
            "img-src 'self' data: blob: https://*.tile.openstreetmap.org",
            "font-src 'self'",
            "connect-src 'self' https://*.pusher.com wss://*.pusher.com",
            "media-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]);

        $response->headers->set('Content-Security-Policy', $csp);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set(
            'Permissions-Policy',
            'geolocation=(self), camera=(), microphone=(), payment=(), usb=()'
        );
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');

        return $response;
    }
}
