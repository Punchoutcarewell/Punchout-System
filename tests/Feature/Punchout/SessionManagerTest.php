<?php

declare(strict_types=1);

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
