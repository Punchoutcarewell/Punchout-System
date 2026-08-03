<?php

declare(strict_types=1);

namespace App\Modules\Punchout\Data;

use App\Modules\Punchout\Enums\PunchoutOperation;

/**
 * Everything extracted from an inbound PunchOutSetupRequest, parsed out of
 * the raw XML by SetupRequestParser. The controller and SessionManager
 * work with this typed shape, never with the XML itself.
 */
final readonly class SetupRequestData
{
    /**
     * @param  array<string, string>  $extrinsics  keyed by Extrinsic name, e.g. "UserEmail" => "..."
     */
    public function __construct(
        public string $fromDomain,
        public string $fromIdentity,
        public string $toDomain,
        public string $toIdentity,
        public string $senderDomain,
        public string $senderIdentity,
        public string $sharedSecret,
        public PunchoutOperation $operation,
        public string $buyerCookie,
        public string $browserFormPostUrl,
        public array $extrinsics,
        public ?string $contactName,
        public ?string $contactEmail,
        public ?string $supplierSetupUrl,
    ) {}

    public function extrinsic(string $name): ?string
    {
        return $this->extrinsics[$name] ?? null;
    }
}
