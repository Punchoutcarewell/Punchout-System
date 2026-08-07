<?php

declare(strict_types=1);

namespace App\Modules\Punchout\Contracts;

use App\Modules\Punchout\Data\OrderMessageData;
use App\Modules\Punchout\Data\OrderRequestData;
use App\Modules\Punchout\Data\OrderResponseData;
use App\Modules\Punchout\Data\SetupRequestData;
use App\Modules\Punchout\Data\SetupResponseData;
use App\Modules\Punchout\Exceptions\MalformedCxmlException;

/**
 * The seam between "this application" and "whatever wire format the buyer's
 * procurement system speaks". Coupa's own configuration only accepts cXML
 * (confirmed against Coupa's documentation, OCI is not an option on their
 * side), so CxmlProtocol is the only implementation this project needs.
 *
 * The interface stays anyway: every controller and service above this
 * layer codes against typed Data objects, never against raw XML strings,
 * and that separation is what makes them unit-testable without a single
 * line of XML in the test. That is a testability decision, not a
 * multi-protocol hedge.
 */
interface PunchoutProtocolInterface
{
    /**
     * @throws MalformedCxmlException
     */
    public function parseSetupRequest(string $rawXml): SetupRequestData;

    public function buildSetupResponse(SetupResponseData $data): string;

    public function buildOrderMessage(OrderMessageData $data): string;

    /**
     * @throws MalformedCxmlException
     */
    public function parseOrderRequest(string $rawXml): OrderRequestData;

    public function buildOrderResponse(OrderResponseData $data): string;
}
