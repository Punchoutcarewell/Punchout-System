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
 * Which specific state renders (direct-visit page vs expired-session page)
 * is a Storefront module concern; this middleware only decides whether to
 * let the request through. Until Storefront exists, it aborts with a
 * plain 403, that placeholder is replaced by the real state pages when
 * that module ships, this middleware's job does not change.
 */
final class RequirePunchoutSession
{
    public function __construct(private readonly SessionManagerInterface $sessions) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->sessions->current() === null) {
            abort(403, 'This catalogue is only accessible through Coupa.');
        }

        return $next($request);
    }
}
