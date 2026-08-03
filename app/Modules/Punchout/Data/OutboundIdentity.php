<?php

declare(strict_types=1);

namespace App\Modules\Punchout\Data;

/**
 * The Header identity fields for an outbound message, reversed from the
 * inbound PunchOutSetupRequest: our own identity (from the matching
 * PunchoutCredential) becomes From, Coupa's identity (captured on the
 * session at setup time) becomes To.
 */
final readonly class OutboundIdentity
{
    public function __construct(
        public string $fromDomain,
        public string $fromIdentity,
        public string $toDomain,
        public string $toIdentity,
        public string $senderDomain,
        public string $senderIdentity,
        public string $deploymentMode,
    ) {}
}
