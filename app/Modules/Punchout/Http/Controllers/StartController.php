<?php

declare(strict_types=1);

namespace App\Modules\Punchout\Http\Controllers;

use App\Modules\Punchout\Contracts\SessionManagerInterface;
use App\Modules\Punchout\Http\Middleware\ResolvePunchoutSession;
use App\Modules\Punchout\Models\PunchoutSession;
use App\Modules\Punchout\Services\CredentialValidator;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * GET /api/punchout/setup/{token}
 *
 * The browser's entry point into the storefront, reached one of two ways:
 *
 * 1. The redirect Coupa performs after PunchOutSetupResponse returns a
 *    StartPage URL, in which case $token is an existing session token
 *    (see SessionManager::start()).
 * 2. Coupa hitting this URL directly with a credential's own shared
 *    secret as $token, no cXML round trip at all, in which case a session
 *    is created on the spot (see SessionManager::startFromSharedSecret()).
 *    The secret is managed in Admin, so changing it there takes effect on
 *    the very next hit, nothing here caches it.
 *
 * $token is tried as a session token first, then as a shared secret, both
 * being opaque strings that cannot be told apart by inspection alone.
 *
 * Binds the session, sets the session cookie with SameSite=None; Secure;
 * Partitioned so it survives Coupa's iframe embedding, and redirects with
 * the session's own token on the URL (there, as a query parameter,
 * unrelated to this route's own shape) as the fallback for a browser that
 * blocks the cookie this same request just tried to set.
 */
final class StartController
{
    public function __construct(
        private readonly SessionManagerInterface $sessions,
        private readonly CredentialValidator $credentials,
    ) {}

    public function handle(string $token): RedirectResponse
    {
        $session = $this->sessions->resolve($token) ?? $this->startFromSharedSecret($token);

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

    private function startFromSharedSecret(string $token): ?PunchoutSession
    {
        $credential = $this->credentials->findActiveBySharedSecret($token);

        return $credential === null ? null : $this->sessions->startFromSharedSecret($credential);
    }
}
