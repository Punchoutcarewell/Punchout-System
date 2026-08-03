<?php

declare(strict_types=1);

namespace App\Modules\Punchout\Http\Controllers;

use App\Modules\Punchout\Contracts\SessionManagerInterface;
use App\Modules\Punchout\Http\Middleware\ResolvePunchoutSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * GET /punchout/start?token=...
 *
 * The browser's entry point into the storefront, reached via the redirect
 * Coupa performs after PunchOutSetupResponse returns a StartPage URL.
 * Binds the session, sets the session cookie with SameSite=None; Secure;
 * Partitioned so it survives Coupa's iframe embedding, and redirects with
 * the token still on the URL as the fallback for a browser that blocks
 * the cookie this same request just tried to set.
 *
 * The redirect destination is a placeholder ('/') until the Storefront
 * module ships its catalogue index route; session resolution and cookie
 * binding, this controller's actual job, are already complete.
 */
final class StartController
{
    public function __construct(private readonly SessionManagerInterface $sessions) {}

    public function handle(Request $request): RedirectResponse
    {
        $token = (string) $request->query('token', '');
        $session = $token !== '' ? $this->sessions->resolve($token) : null;

        if ($session === null) {
            return redirect('/')->with('punchout_error', 'expired_or_invalid_token');
        }

        $this->sessions->bind($session);

        $response = redirect('/?'.ResolvePunchoutSession::QUERY_PARAM.'='.urlencode($session->token));

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
