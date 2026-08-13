<?php

declare(strict_types=1);

namespace App\Modules\Punchout\Http\Controllers;

use App\Modules\Punchout\Contracts\SessionManagerInterface;
use App\Modules\Punchout\Http\Middleware\ResolvePunchoutSession;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * GET /punchout/setup/{token}
 *
 * The browser's entry point into the storefront, reached via the redirect
 * Coupa performs after PunchOutSetupResponse returns a StartPage URL. The
 * token is a required path segment, not a query parameter, see routes.php
 * for why this shares a path prefix with POST /punchout/setup without
 * being the same route.
 *
 * Binds the session, sets the session cookie with SameSite=None; Secure;
 * Partitioned so it survives Coupa's iframe embedding, and redirects with
 * the token still on the URL (there, as a query parameter, unrelated to
 * this route's own shape) as the fallback for a browser that blocks the
 * cookie this same request just tried to set.
 */
final class StartController
{
    public function __construct(private readonly SessionManagerInterface $sessions) {}

    public function handle(string $token): RedirectResponse
    {
        $session = $this->sessions->resolve($token);

        if ($session === null) {
            return redirect()->route('storefront.session-expired');
        }

        $this->sessions->bind($session);

        $response = redirect()->route('storefront.catalog', [ResolvePunchoutSession::QUERY_PARAM => $session->token]);

        $response->headers->setCookie(Cookie::create(
            name: ResolvePunchoutSession::COOKIE_NAME,
            value: $session->token,
            expire: $session->expires_at,
            path: '/',
            domain: null,
            secure: true,
            httpOnly: true,
            raw: false,
            sameSite: 'none',
            partitioned: true,
        ));

        return $response;
    }
}
