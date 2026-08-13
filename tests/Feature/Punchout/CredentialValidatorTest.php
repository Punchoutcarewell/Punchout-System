<?php

declare(strict_types=1);

use App\Modules\Punchout\Data\CxmlHeaderData;
use App\Modules\Punchout\Exceptions\InvalidCredentialsException;
use App\Modules\Punchout\Services\CredentialValidator;
use Illuminate\Support\Facades\DB;

function setupRequestDataFor(string $sharedSecret): CxmlHeaderData
{
    return new CxmlHeaderData(
        fromDomain: 'DUNS',
        fromIdentity: 'COUPA1',
        toDomain: 'DUNS',
        toIdentity: '079928354',
        senderDomain: 'DUNS',
        senderIdentity: 'COUPA1',
        sharedSecret: $sharedSecret,
    );
}

it('validates against an active matching credential', function () {
    createTestPunchoutCredential('ALD');

    $credential = (new CredentialValidator)->validate(setupRequestDataFor('ALD'));

    expect($credential->to_identity)->toBe('079928354');
});

it('rejects a mismatched shared secret', function () {
    createTestPunchoutCredential('ALD');

    (new CredentialValidator)->validate(setupRequestDataFor('WRONG-SECRET'));
})->throws(InvalidCredentialsException::class);

it('rejects the correct shared secret presented with an unrecognised From identity', function () {
    createTestPunchoutCredential('ALD');

    $header = new CxmlHeaderData(
        fromDomain: 'DUNS',
        fromIdentity: 'SOME-OTHER-BUYER',
        toDomain: 'DUNS',
        toIdentity: '079928354',
        senderDomain: 'DUNS',
        senderIdentity: 'COUPA1',
        sharedSecret: 'ALD',
    );

    (new CredentialValidator)->validate($header);
})->throws(InvalidCredentialsException::class);

it('rejects when no credential is configured for the identity', function () {
    (new CredentialValidator)->validate(setupRequestDataFor('ALD'));
})->throws(InvalidCredentialsException::class);

it('rejects an inactive credential even with the correct secret', function () {
    $credential = createTestPunchoutCredential('ALD');
    $credential->update(['is_active' => false]);

    (new CredentialValidator)->validate(setupRequestDataFor('ALD'));
})->throws(InvalidCredentialsException::class);

it('rejects a Test credential when the application is running in production', function () {
    createTestPunchoutCredential('ALD');

    app()->instance('env', 'production');

    try {
        (new CredentialValidator)->validate(setupRequestDataFor('ALD'));
    } finally {
        app()->instance('env', 'testing');
    }
})->throws(InvalidCredentialsException::class);

it('encrypts the shared secret at rest', function () {
    $credential = createTestPunchoutCredential('ALD');

    $rawColumnValue = DB::table('punchout_credentials')->where('id', $credential->id)->value('shared_secret');

    expect($rawColumnValue)->not->toBe('ALD');
});

it('finds an active credential by its shared secret alone, with no From/To identity given', function () {
    createTestPunchoutCredential('ALD');

    $credential = (new CredentialValidator)->findActiveBySharedSecret('ALD');

    expect($credential)->not->toBeNull()
        ->and($credential->to_identity)->toBe('079928354');
});

it('returns null rather than throwing for an unrecognised secret', function () {
    createTestPunchoutCredential('ALD');

    expect((new CredentialValidator)->findActiveBySharedSecret('not-a-real-secret'))->toBeNull();
});

it('does not find an inactive credential by its shared secret', function () {
    $credential = createTestPunchoutCredential('ALD');
    $credential->update(['is_active' => false]);

    expect((new CredentialValidator)->findActiveBySharedSecret('ALD'))->toBeNull();
});
