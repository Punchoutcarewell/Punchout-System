<?php

declare(strict_types=1);

/**
 * The storefront runs cross-site inside Coupa's iframe, so Laravel's own
 * session cookie needs SameSite=None; Secure; Partitioned, the same
 * posture already hand-built for the punchout session cookie in
 * StartController. This asserts config/session.php's env-driven values
 * actually reach the emitted cookie, so a future change to .env or
 * config/session.php that quietly regresses this fails the suite instead
 * of only failing against real Coupa traffic.
 */
it('emits the session cookie with SameSite=None, Secure, and Partitioned', function () {
    $response = $this->get('/storefront');

    $cookie = collect($response->headers->getCookies())
        ->first(fn ($cookie) => $cookie->getName() === config('session.cookie'));

    expect($cookie)->not->toBeNull()
        ->and($cookie->getSameSite())->toBe('none')
        ->and($cookie->isSecure())->toBeTrue()
        ->and($cookie->isPartitioned())->toBeTrue();
});
