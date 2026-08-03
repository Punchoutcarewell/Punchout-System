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
