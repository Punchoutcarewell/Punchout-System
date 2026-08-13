<?php

declare(strict_types=1);

namespace App\Modules\Punchout\Services;

use App\Modules\Punchout\Data\CxmlHeaderData;
use App\Modules\Punchout\Enums\PunchoutEnvironment;
use App\Modules\Punchout\Exceptions\InvalidCredentialsException;
use App\Modules\Punchout\Models\PunchoutCredential;
use Illuminate\Support\Collection;

/**
 * Authenticates an inbound cXML message (PunchOutSetupRequest or
 * OrderRequest, both parsed down to the same CxmlHeaderData shape) against
 * the credential configured for the From/To identity pair it claims.
 * Comparison is always via hash_equals(), never a plain string comparison:
 * timing-safe comparison is the whole reason this class exists rather
 * than an inline check in the controller.
 *
 * Matching on from_domain/from_identity as well as to_domain/to_identity
 * means a caller holding one buyer's shared secret can never authenticate
 * as, or address an outbound message to, a different buyer: an
 * unrecognised From is exactly as much a failure as a wrong secret, not a
 * detail reflected verbatim into the session.
 */
final class CredentialValidator
{
    public function validate(CxmlHeaderData $header): PunchoutCredential
    {
        $credential = PunchoutCredential::query()
            ->where('environment', PunchoutEnvironment::current())
            ->where('from_domain', $header->fromDomain)
            ->where('from_identity', $header->fromIdentity)
            ->where('to_domain', $header->toDomain)
            ->where('to_identity', $header->toIdentity)
            ->where('is_active', true)
            ->first();

        if ($credential === null) {
            throw InvalidCredentialsException::withContext(
                'No active credential is configured for the requested identity.',
                [
                    'from_domain' => $header->fromDomain,
                    'from_identity' => $header->fromIdentity,
                    'to_domain' => $header->toDomain,
                    'to_identity' => $header->toIdentity,
                ],
            );
        }

        if (! hash_equals((string) $credential->shared_secret, $header->sharedSecret)) {
            throw InvalidCredentialsException::withContext(
                'The shared secret did not match.',
                ['to_domain' => $header->toDomain, 'to_identity' => $header->toIdentity],
            );
        }

        return $credential;
    }

    /**
     * Finds the active credential whose secret matches $secret alone, with
     * no From/To identity to narrow the search first: used by
     * StartController's GET /api/punchout/setup/{secret}, which has no
     * cXML header, only a bare string off the URL. Every active credential
     * in the current environment is compared via hash_equals() in turn,
     * rather than a WHERE on shared_secret, since the column is encrypted
     * at rest and never queryable by its plaintext value. Always reads the
     * current row, so changing a credential's secret in Admin takes effect
     * on the very next request, nothing here caches it.
     */
    public function findActiveBySharedSecret(string $secret): ?PunchoutCredential
    {
        return $this->activeCredentialsForCurrentEnvironment()
            ->first(fn (PunchoutCredential $credential): bool => hash_equals((string) $credential->shared_secret, $secret));
    }

    /**
     * @return Collection<int, PunchoutCredential>
     */
    private function activeCredentialsForCurrentEnvironment(): Collection
    {
        return PunchoutCredential::query()
            ->where('environment', PunchoutEnvironment::current())
            ->where('is_active', true)
            ->get();
    }
}
