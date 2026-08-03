<?php

declare(strict_types=1);

use App\Modules\Punchout\Data\SetupRequestData;
use App\Modules\Punchout\Enums\PunchoutOperation;
use App\Modules\Punchout\Exceptions\InvalidCredentialsException;
use App\Modules\Punchout\Services\CredentialValidator;
use Illuminate\Support\Facades\DB;

function setupRequestDataFor(string $sharedSecret): SetupRequestData
{
    return new SetupRequestData(
        fromDomain: 'DUNS',
        fromIdentity: 'COUPA1',
        toDomain: 'DUNS',
        toIdentity: '079928354',
        senderDomain: 'DUNS',
        senderIdentity: 'COUPA1',
        sharedSecret: $sharedSecret,
        operation: PunchoutOperation::Create,
        buyerCookie: 'cookie123',
        browserFormPostUrl: 'https://example.com/cart',
        extrinsics: [],
        contactName: null,
        contactEmail: null,
        supplierSetupUrl: null,
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

it('rejects when no credential is configured for the identity', function () {
    (new CredentialValidator)->validate(setupRequestDataFor('ALD'));
})->throws(InvalidCredentialsException::class);

it('rejects an inactive credential even with the correct secret', function () {
    $credential = createTestPunchoutCredential('ALD');
    $credential->update(['is_active' => false]);

    (new CredentialValidator)->validate(setupRequestDataFor('ALD'));
})->throws(InvalidCredentialsException::class);

it('encrypts the shared secret at rest', function () {
    $credential = createTestPunchoutCredential('ALD');

    $rawColumnValue = DB::table('punchout_credentials')->where('id', $credential->id)->value('shared_secret');

    expect($rawColumnValue)->not->toBe('ALD');
});
