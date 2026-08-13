<?php

declare(strict_types=1);

namespace App\Modules\Punchout\Http\Controllers;

use App\Modules\Orders\Contracts\PurchaseOrderServiceInterface;
use App\Modules\Punchout\Contracts\PunchoutProtocolInterface;
use App\Modules\Punchout\Data\CxmlHeaderData;
use App\Modules\Punchout\Data\OrderResponseData;
use App\Modules\Punchout\Enums\PunchoutMessageType;
use App\Modules\Punchout\Exceptions\InvalidCredentialsException;
use App\Modules\Punchout\Exceptions\MalformedCxmlException;
use App\Modules\Punchout\Models\PunchoutLog;
use App\Modules\Punchout\Models\PunchoutSession;
use App\Modules\Punchout\Services\CredentialValidator;
use App\Modules\Punchout\Services\PunchoutLogger;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * POST /api/punchout/order
 *
 * Receives the purchase order Coupa sends after the buyer submits their
 * requisition (cXML OrderRequest), if cXML ends up being the PO
 * transmission channel GPCS and Carewell agree on; that decision is still
 * open (see the roadmap's blocking questions). The raw payload is
 * persisted before parsing is attempted, so a parser bug can never lose
 * an order.
 *
 * Authenticated the same way as PunchOutSetupRequest, via the same
 * Header/Sender/Credential/SharedSecret and CredentialValidator: an
 * inbound OrderRequest carries a complete cXML Header exactly like the
 * setup request does, so there is no reason this endpoint should accept
 * unauthenticated input while /api/punchout/setup does not. The safe default
 * while that stayed unconfirmed was closed, not open.
 *
 * Turning the parsed OrderRequestData into a PurchaseOrder business record
 * is a single call to Orders\Contracts\PurchaseOrderServiceInterface: this
 * controller's own job stays receive, log, validate the XML, and
 * acknowledge, it has no idea what a PurchaseOrder does with the data
 * afterward.
 */
final class OrderRequestController
{
    public function __construct(
        private readonly PunchoutProtocolInterface $protocol,
        private readonly CredentialValidator $credentials,
        private readonly PunchoutLogger $logger,
        private readonly PurchaseOrderServiceInterface $purchaseOrders,
    ) {}

    public function handle(Request $request): Response
    {
        $rawXml = $request->getContent();

        $log = $this->logger->logInbound(PunchoutMessageType::OrderRequest, $rawXml);

        try {
            return $this->process($rawXml, $log);
        } catch (Throwable $exception) {
            // See SetupController::handle() for why this net exists and
            // deliberately never calls back into $this->protocol: that
            // dependency may be exactly what just failed.
            try {
                Log::channel('punchout')->error('Unexpected failure handling OrderRequest.', [
                    'error' => $exception->getMessage(),
                ]);

                $this->logger->updateStatus($log, 500, $exception->getMessage());
            } catch (Throwable) {
                // Logging itself failing must never stop the fallback
                // response below from reaching Coupa.
            }

            return $this->xmlResponse(self::genericFailureXml(500, 'Internal error.'), 500);
        }
    }

    /**
     * A hand written cXML error, deliberately independent of
     * PunchoutProtocolInterface: this is the fallback for when something
     * entirely unanticipated has already gone wrong, so it must not risk
     * failing a second time by calling back into the same dependency.
     */
    private static function genericFailureXml(int $statusCode, string $statusText): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<!DOCTYPE cXML SYSTEM "http://xml.cxml.org/schemas/cXML/1.2.014/cXML.dtd">'
            ."<cXML><Response><Status code=\"{$statusCode}\" text=\"{$statusText}\"/></Response></cXML>";
    }

    private function process(string $rawXml, PunchoutLog $log): Response
    {
        try {
            $data = $this->protocol->parseOrderRequest($rawXml);
        } catch (MalformedCxmlException $exception) {
            $this->logger->updateStatus($log, 400, $exception->getMessage());

            return $this->xmlResponse(
                $this->protocol->buildOrderResponse(new OrderResponseData(400, 'Malformed request.')),
                400,
            );
        }

        try {
            $this->credentials->validate(CxmlHeaderData::fromOrderRequest($data));
        } catch (InvalidCredentialsException $exception) {
            $this->logger->updateStatus($log, 401, $exception->getMessage());

            return $this->xmlResponse(
                $this->protocol->buildOrderResponse(new OrderResponseData(401, 'Unauthorized.')),
                401,
            );
        }

        $this->purchaseOrders->receive($data, $rawXml, $this->resolvePunchoutSessionId($data->buyerCookie));

        $responseXml = $this->protocol->buildOrderResponse(OrderResponseData::accepted());

        $this->logger->updateStatus($log, 200);
        $this->logger->logOutbound(PunchoutMessageType::OrderResponse, $responseXml, httpStatus: 200);

        return $this->xmlResponse($responseXml, 200);
    }

    private function xmlResponse(string $xml, int $status): Response
    {
        return response($xml, $status)->header('Content-Type', 'text/xml; charset=UTF-8');
    }

    /**
     * Best-effort only: $buyerCookie is null whenever Coupa's OrderRequest
     * does not echo it back (the expected case until a real specimen
     * confirms otherwise, see OrderRequestParser). A miss here is not an
     * error, it just means this PurchaseOrder is never linked back to the
     * punchout_session that produced it.
     */
    private function resolvePunchoutSessionId(?string $buyerCookie): ?int
    {
        if ($buyerCookie === null) {
            return null;
        }

        $id = PunchoutSession::query()->where('buyer_cookie', $buyerCookie)->value('id');

        return $id !== null ? (int) $id : null;
    }
}
