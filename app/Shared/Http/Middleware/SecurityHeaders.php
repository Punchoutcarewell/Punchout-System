<?php

declare(strict_types=1);

namespace App\Shared\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline security headers applied to every response, registered as
 * global middleware in bootstrap/app.php rather than the "web" group
 * alone, so it also covers /punchout/setup and /punchout/order, which
 * deliberately run outside "web" (see Punchout\routes.php).
 *
 * Deliberately does not set X-Frame-Options: this storefront is meant to
 * be embedded in Coupa's iframe, Punchout\Http\Middleware\FrameAncestors
 * already controls that per-route via the modern CSP frame-ancestors
 * directive, which every browser that still honours X-Frame-Options also
 * honours frame-ancestors for, and X-Frame-Options only supports a single
 * origin or DENY/SAMEORIGIN, it cannot express "these specific domains".
 */
final class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        return $response;
    }
}
