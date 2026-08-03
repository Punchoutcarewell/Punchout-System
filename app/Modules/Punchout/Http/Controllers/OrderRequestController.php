<?php

declare(strict_types=1);

namespace App\Modules\Punchout\Http\Controllers;

use App\Modules\Punchout\Cxml\CxmlProtocol;
use App\Modules\Punchout\Data\OrderResponseData;
use App\Modules\Punchout\Enums\PunchoutMessageType;
use App\Modules\Punchout\Exceptions\MalformedCxmlException;
use App\Modules\Punchout\Services\PunchoutLogger;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * POST /punchout/order
 *
 * Receives the purchase order Coupa sends after the buyer submits their
 * requisition (cXML OrderRequest), if cXML ends up being the PO
 * transmission channel GPCS and Carewell agree on; that decision is still
 * open (see the roadmap's blocking questions). The raw payload is
 * persisted before parsing is attempted, so a parser bug can never lose
 * an order.
 *
 * Turning the parsed OrderRequestData into a PurchaseOrder business record
 * belongs to the Orders module, which does not exist yet. This endpoint
 * already does everything that is this module's job: receive, log,
 * validate the XML, and acknowledge. Wiring the Orders module in is a
 * single call to a PurchaseOrderService once that module ships; nothing
 * about this controller's own responsibilities changes when that happens.
 */
final class OrderRequestController
{
    public function __construct(
        private readonly CxmlProtocol $protocol,
        private readonly PunchoutLogger $logger,
    ) {}

    public function handle(Request $request): Response
    {
        $rawXml = $request->getContent();

        $this->logger->logInbound(PunchoutMessageType::OrderRequest, $rawXml);

        try {
            $this->protocol->parseOrderRequest($rawXml);
        } catch (MalformedCxmlException $exception) {
            $this->logger->logInbound(PunchoutMessageType::OrderRequest, $rawXml, httpStatus: 400, error: $exception->getMessage());

            return $this->xmlResponse(
                $this->protocol->buildOrderResponse(new OrderResponseData(400, 'Malformed request.')),
                400,
            );
        }

        $responseXml = $this->protocol->buildOrderResponse(OrderResponseData::accepted());

        $this->logger->logOutbound(PunchoutMessageType::OrderResponse, $responseXml, httpStatus: 200);

        return $this->xmlResponse($responseXml, 200);
    }

    private function xmlResponse(string $xml, int $status): Response
    {
        return response($xml, $status)->header('Content-Type', 'text/xml; charset=UTF-8');
    }
}
