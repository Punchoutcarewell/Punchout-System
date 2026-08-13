<?php

declare(strict_types=1);

use App\Modules\Punchout\Enums\PunchoutSessionStatus;
use App\Modules\Punchout\Http\Middleware\ResolvePunchoutSession;
use App\Modules\Punchout\Models\PunchoutSession;

function issuePunchoutTokenForStartTest(): string
{
    createTestPunchoutCredential('ALD');

    $xml = (string) file_get_contents(base_path('tests/Fixtures/Cxml/setup_request.xml'));
    test()->call('POST', '/api/punchout/setup', content: $xml, server: ['CONTENT_TYPE' => 'text/xml']);

    return PunchoutSession::query()->firstOrFail()->token;
}

it('binds the session and sets a SameSite=None; Secure; Partitioned cookie', function () {
    $token = issuePunchoutTokenForStartTest();

    $response = $this->get("/api/punchout/setup/{$token}");

    $response->assertRedirect();

    // assertCookie() decrypts before comparing: the "web" middleware group
    // encrypts every outgoing cookie value by default, which is a welcome
    // extra layer here, not something worth turning off for this cookie.
    $response->assertCookie(ResolvePunchoutSession::COOKIE_NAME, $token);

    $cookie = collect($response->headers->getCookies())
        ->first(fn ($c) => $c->getName() === ResolvePunchoutSession::COOKIE_NAME);

    expect($cookie)->not->toBeNull()
        ->and($cookie->isSecure())->toBeTrue()
        ->and($cookie->isHttpOnly())->toBeTrue()
        ->and($cookie->getSameSite())->toBe('none')
        ->and($cookie->isPartitioned())->toBeTrue();
});

it('redirects with an error and sets no cookie for an unknown token', function () {
    $response = $this->get('/api/punchout/setup/does-not-exist');

    $response->assertRedirect();

    $cookie = collect($response->headers->getCookies())
        ->first(fn ($c) => $c->getName() === ResolvePunchoutSession::COOKIE_NAME);

    expect($cookie)->toBeNull();
});

it('has no route at all for a request with no token segment, the token is required', function () {
    // GET on the exact path /api/punchout/setup (POST-only, PunchOutSetupRequest)
    // matches that URI but not this method, a 405, not a 404: proof the
    // two /api/punchout/setup routes really are disambiguated by method
    // rather than one silently shadowing the other.
    $response = $this->get('/api/punchout/setup');

    $response->assertStatus(405);
});

it('does not resolve an expired session and marks it expired', function () {
    $token = issuePunchoutTokenForStartTest();

    PunchoutSession::query()->where('token', $token)->update(['expires_at' => now()->subMinute()]);

    $response = $this->get("/api/punchout/setup/{$token}");

    $response->assertRedirect();

    $session = PunchoutSession::query()->where('token', $token)->firstOrFail();
    expect($session->status)->toBe(PunchoutSessionStatus::Expired);
});
