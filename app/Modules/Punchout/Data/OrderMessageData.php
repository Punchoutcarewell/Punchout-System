<?php

declare(strict_types=1);

namespace App\Modules\Punchout\Data;

/**
 * Everything OrderMessageBuilder needs to render a PunchOutOrderMessage:
 * the header identity fields (mirrored from the original setup request)
 * plus the cart itself.
 *
 * buyerCookie must be the exact value captured from the originating
 * PunchOutSetupRequest. It is never regenerated or reformatted: a
 * mismatch causes Coupa to silently discard the cart.
 *
 * deploymentMode describes which Coupa environment this message targets
 * ("test" or "production"), driven by which PunchoutCredential
 * environment the session authenticated against. This is deliberately
 * separate from Laravel's own APP_ENV: the two describe different things,
 * and this application only ever runs as one Laravel environment at a
 * time regardless of which Coupa environment a given session is talking
 * to.
 */
final readonly class OrderMessageData
{
    public function __construct(
        public string $fromDomain,
        public string $fromIdentity,
        public string $toDomain,
        public string $toIdentity,
        public string $senderDomain,
        public string $senderIdentity,
        public string $buyerCookie,
        public CartSnapshot $cart,
        public string $deploymentMode = 'test',
        public string $operationAllowed = 'edit',
        public string $quoteStatus = 'final',
    ) {}
}
