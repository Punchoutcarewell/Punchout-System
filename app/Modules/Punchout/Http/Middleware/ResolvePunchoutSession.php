<?php

declare(strict_types=1);

namespace App\Modules\Punchout\Http\Middleware;

use App\Modules\Punchout\Contracts\SessionManagerInterface;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the active PunchoutSession from either the session cookie or
 * the "token" query parameter, cookie first, query string as fallback.
 * This is not an edge case handled later: it is how session resolution
 * always works, because Coupa's iframe embedding can run in a browser
 * with third-party cookies blocked, and a session that only worked via
 * cookie would go silently blank in exactly that scenario.
 *
 * Does not reject the request if no session is found, that is
 * RequirePunchoutSession's job. This middleware only resolves and binds.
 */
final class ResolvePunchoutSession
{
    public const COOKIE_NAME = 'punchout_session';

    public const QUERY_PARAM = 'token';

    public function __construct(private readonly SessionManagerInterface $sessions) {}

    public function handle(Request $request, Closure $next): Response
    {
        $cookieToken = $request->cookie(self::COOKIE_NAME);
        $queryToken = $request->query(self::QUERY_PARAM);

        $token = is_string($cookieToken) && $cookieToken !== '' ? $cookieToken : $queryToken;
        $resolvedFromQueryOnly = ! (is_string($cookieToken) && $cookieToken !== '') && is_string($queryToken) && $queryToken !== '';

        if (is_string($token) && $token !== '') {
            $session = $this->sessions->resolve($token);

            if ($session !== null) {
                $this->sessions->bind($session);
            }
        }

        $response = $next($request);

        $current = $this->sessions->current();

        // A cookie-blocked browser resolves through the query string on every
        // request. Re-issuing the cookie on each response costs nothing and
        // means the session recovers to cookie-based tracking the moment
        // cookies do become available, without waiting for a fresh /start visit.
        if ($current !== null && $resolvedFromQueryOnly) {
            $response->headers->setCookie(Cookie::create(
                name: self::COOKIE_NAME,
                value: $current->token,
                expire: $current->expires_at,
                path: '/',
                domain: null,
                secure: true,
                httpOnly: true,
                raw: false,
                sameSite: 'none',
                partitioned: true,
            ));
        }

        return $response;
    }
}
