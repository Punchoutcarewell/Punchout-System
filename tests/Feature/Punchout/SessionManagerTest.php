<?php

declare(strict_types=1);

use App\Modules\Punchout\Enums\PunchoutSessionStatus;
use App\Modules\Punchout\Exceptions\InvalidCredentialsException;
use App\Modules\Punchout\Models\PunchoutCredential;
use App\Modules\Punchout\Services\SessionManager;

it('builds the reversed outbound identity from the matching credential and the session\'s captured inbound identity', function () {
    $session = issueTestPunchoutSession();

    $identity = app(SessionManager::class)->resolveOutboundIdentity($session);

    expect($identity->fromDomain)->toBe('DUNS')
        ->and($identity->fromIdentity)->toBe('079928354')
        ->and($identity->toDomain)->toBe('DUNS')
        ->and($identity->toIdentity)->toBe('COUPA1')
        ->and($identity->senderDomain)->toBe('DUNS')
        ->and($identity->senderIdentity)->toBe('COUPA1')
        ->and($identity->deploymentMode)->toBe('test');
});

it('throws when no active credential matches the session\'s to_domain and to_identity', function () {
    $session = issueTestPunchoutSession();

    PunchoutCredential::query()->update(['is_active' => false]);

    app(SessionManager::class)->resolveOutboundIdentity($session);
})->throws(InvalidCredentialsException::class);

it('still resolves a Transferring session within its grace window', function () {
    $session = issueTestPunchoutSession();
    $manager = app(SessionManager::class);

    $manager->markTransferring($session);

    expect($manager->resolve($session->token))->not->toBeNull();
});

it('does not resolve a Transferring session once its grace window has lapsed, and lazily marks it Transferred', function () {
    $session = issueTestPunchoutSession();
    $manager = app(SessionManager::class);

    $manager->markTransferring($session);
    $session->update(['transferring_at' => now()->subMinutes((int) config('punchout.transfer_grace_minutes', 10) + 1)]);

    expect($manager->resolve($session->token))->toBeNull()
        ->and($session->fresh()->status->value)->toBe('transferred');
});

it('builds a usable preview session directly from a credential, with no cXML round trip', function () {
    $credential = createTestPunchoutCredential('ALD');
    $manager = app(SessionManager::class);

    $session = $manager->startPreview($credential, 'My preview');

    expect($session->is_preview)->toBeTrue()
        ->and($session->status)->toBe(PunchoutSessionStatus::Active)
        ->and($session->from_domain)->toBe($credential->from_domain)
        ->and($session->from_identity)->toBe($credential->from_identity)
        ->and($session->to_domain)->toBe($credential->to_domain)
        ->and($session->to_identity)->toBe($credential->to_identity)
        ->and($session->buyer_unique_name)->toBe('My preview')
        ->and($manager->resolve($session->token))->not->toBeNull();
});

it('resolves a preview session\'s outbound identity, since its identity fields are copied straight from a real credential', function () {
    $credential = createTestPunchoutCredential('ALD');
    $manager = app(SessionManager::class);

    $session = $manager->startPreview($credential, 'My preview');

    expect(fn () => $manager->resolveOutboundIdentity($session))->not->toThrow(InvalidCredentialsException::class);
});

it('builds a real, non-preview session directly from a credential\'s shared secret, no cXML round trip', function () {
    $credential = createTestPunchoutCredential('ALD');
    $credential->update(['browser_form_post_url' => 'https://coupa.example.com/cart/transfer']);
    $manager = app(SessionManager::class);

    $session = $manager->startFromSharedSecret($credential);

    expect($session->is_preview)->toBeFalse()
        ->and($session->status)->toBe(PunchoutSessionStatus::Active)
        ->and($session->browser_form_post_url)->toBe('https://coupa.example.com/cart/transfer')
        ->and($session->from_domain)->toBe($credential->from_domain)
        ->and($session->from_identity)->toBe($credential->from_identity)
        ->and($session->to_domain)->toBe($credential->to_domain)
        ->and($session->to_identity)->toBe($credential->to_identity)
        ->and($session->buyer_cookie)->not->toBeEmpty();
});

it('creates a fresh session every time startFromSharedSecret is called for the same credential', function () {
    $credential = createTestPunchoutCredential('ALD');
    $credential->update(['browser_form_post_url' => 'https://coupa.example.com/cart/transfer']);
    $manager = app(SessionManager::class);

    $first = $manager->startFromSharedSecret($credential);
    $second = $manager->startFromSharedSecret($credential);

    expect($first->token)->not->toBe($second->token);
});
