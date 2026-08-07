<?php

declare(strict_types=1);

namespace App\Modules\Punchout\Cxml;

use App\Modules\Punchout\Contracts\PunchoutProtocolInterface;
use App\Modules\Punchout\Data\OrderMessageData;
use App\Modules\Punchout\Data\OrderRequestData;
use App\Modules\Punchout\Data\OrderResponseData;
use App\Modules\Punchout\Data\SetupRequestData;
use App\Modules\Punchout\Data\SetupResponseData;

/**
 * The cXML implementation of PunchoutProtocolInterface. Composes the
 * individual parser/builder classes rather than containing any XML
 * handling itself, each of those classes is independently unit-testable
 * against the golden fixtures.
 */
final class CxmlProtocol implements PunchoutProtocolInterface
{
    public function __construct(
        private readonly SetupRequestParser $setupRequestParser = new SetupRequestParser,
        private readonly SetupResponseBuilder $setupResponseBuilder = new SetupResponseBuilder,
        private readonly OrderMessageBuilder $orderMessageBuilder = new OrderMessageBuilder,
        private readonly OrderRequestParser $orderRequestParser = new OrderRequestParser,
        private readonly OrderResponseBuilder $orderResponseBuilder = new OrderResponseBuilder,
    ) {}

    public function parseSetupRequest(string $rawXml): SetupRequestData
    {
        return $this->setupRequestParser->parse($rawXml);
    }

    public function buildSetupResponse(SetupResponseData $data): string
    {
        return $this->setupResponseBuilder->build($data);
    }

    public function buildOrderMessage(OrderMessageData $data): string
    {
        return $this->orderMessageBuilder->build($data);
    }

    public function parseOrderRequest(string $rawXml): OrderRequestData
    {
        return $this->orderRequestParser->parse($rawXml);
    }

    public function buildOrderResponse(OrderResponseData $data): string
    {
        return $this->orderResponseBuilder->build($data);
    }
}
