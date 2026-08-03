<?php

declare(strict_types=1);

namespace App\Modules\Punchout\Services;

use App\Modules\Punchout\Data\SetupRequestData;
use App\Modules\Punchout\Exceptions\InvalidCredentialsException;
use App\Modules\Punchout\Models\PunchoutCredential;

/**
 * Authenticates an inbound PunchOutSetupRequest against the credential
 * configured for the identity it claims to address. Comparison is always
 * via hash_equals(), never a plain string comparison: timing-safe
 * comparison is the whole reason this class exists rather than an inline
 * check in the controller.
 */
final class CredentialValidator
{
    public function validate(SetupRequestData $data): PunchoutCredential
    {
        $credential = PunchoutCredential::query()
            ->where('to_domain', $data->toDomain)
            ->where('to_identity', $data->toIdentity)
            ->where('is_active', true)
            ->first();

        if ($credential === null) {
            throw InvalidCredentialsException::withContext(
                'No active credential is configured for the requested identity.',
                ['to_domain' => $data->toDomain, 'to_identity' => $data->toIdentity],
            );
        }

        if (! hash_equals((string) $credential->shared_secret, $data->sharedSecret)) {
            throw InvalidCredentialsException::withContext(
                'The shared secret did not match.',
                ['to_domain' => $data->toDomain, 'to_identity' => $data->toIdentity],
            );
        }

        return $credential;
    }
}
