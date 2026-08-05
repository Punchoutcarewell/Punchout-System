<?php

declare(strict_types=1);

namespace App\Modules\Punchout\Data;

/**
 * The From/To/Sender credentials common to every cXML message's Header,
 * parsed once by CxmlHeaderParser and reused by both PunchOutSetupRequest
 * and OrderRequest, the two inbound message types this app authenticates.
 */
final readonly class CxmlHeaderData
{
    public function __construct(
        public string $fromDomain,
        public string $fromIdentity,
        public string $toDomain,
        public string $toIdentity,
        public string $senderDomain,
        public string $senderIdentity,
        public string $sharedSecret,
    ) {}

    public static function fromSetupRequest(SetupRequestData $data): self
    {
        return new self(
            $data->fromDomain,
            $data->fromIdentity,
            $data->toDomain,
            $data->toIdentity,
            $data->senderDomain,
            $data->senderIdentity,
            $data->sharedSecret,
        );
    }

    public static function fromOrderRequest(OrderRequestData $data): self
    {
        return new self(
            $data->fromDomain,
            $data->fromIdentity,
            $data->toDomain,
            $data->toIdentity,
            $data->senderDomain,
            $data->senderIdentity,
            $data->sharedSecret,
        );
    }
}
