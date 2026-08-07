<?php

declare(strict_types=1);

namespace App\Modules\Punchout\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the Content-Security-Policy frame-ancestors directive so the
 * storefront can only be embedded in an iframe on Coupa's own domain(s).
 * The domain list is config-driven (punchout.coupa_frame_ancestors), since
 * Coupa's exact test and production domains are still an open question
 * for GPCS. Until that is answered, this defaults to denying all framing
 * rather than leaving it open: a secure default that becomes a one-line
 * config change once the domain is confirmed.
 */
final class FrameAncestors
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $domains = array_filter((array) config('punchout.coupa_frame_ancestors', []));

        $directive = $domains === []
            ? "frame-ancestors 'none'"
            : 'frame-ancestors '.implode(' ', $domains);

        $response->headers->set('Content-Security-Policy', $directive);

        return $response;
    }
}
