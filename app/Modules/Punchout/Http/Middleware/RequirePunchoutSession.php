<?php

declare(strict_types=1);

namespace App\Modules\Punchout\Http\Middleware;

use App\Modules\Punchout\Contracts\SessionManagerInterface;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards every storefront and cart route: a request with no bound session
 * (direct visit, expired session, or cookie-blocked with no working token
 * fallback) never reaches storefront content.
 *
 * Redirects to one of two distinct Storefront pages, never a generic
 * error: "direct visit, no token" (nothing was ever presented) is a
 * different message from "session expired" (a token was presented but
 * didn't resolve), see ResolvePunchoutSession for how that distinction is
 * tracked. This module still doesn't know what those pages look like,
 * only their route names.
 */
final class RequirePunchoutSession
{
    public function __construct(private readonly SessionManagerInterface $sessions) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->sessions->current() === null) {
            $routeName = $request->attributes->get('punchout_had_token', false)
                ? 'storefront.session-expired'
                : 'storefront.no-token';

            return redirect()->route($routeName);
        }

        return $next($request);
    }
}
