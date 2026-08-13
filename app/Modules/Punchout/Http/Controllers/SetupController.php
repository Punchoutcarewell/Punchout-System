<?php

declare(strict_types=1);

namespace App\Modules\Punchout\Http\Controllers;

use App\Modules\Punchout\Contracts\PunchoutProtocolInterface;
use App\Modules\Punchout\Contracts\SessionManagerInterface;
use App\Modules\Punchout\Data\CxmlHeaderData;
use App\Modules\Punchout\Data\SetupResponseData;
use App\Modules\Punchout\Enums\PunchoutMessageType;
use App\Modules\Punchout\Exceptions\InvalidCredentialsException;
use App\Modules\Punchout\Exceptions\MalformedCxmlException;
use App\Modules\Punchout\Services\CredentialValidator;
use App\Modules\Punchout\Services\PunchoutLogger;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * POST /api/punchout/setup
 *
 * No CSRF, no session middleware: this is a raw XML endpoint Coupa's
 * server posts to directly, not a browser request. Every response,
 * success or failure, is well-formed cXML carrying the right Status code,
 * never an HTML error page, Coupa surfaces the Status text to the buyer
 * verbatim.
 */
final class SetupController
{
    public function __construct(
        private readonly PunchoutProtocolInterface $protocol,
        private readonly CredentialValidator $credentials,
        private readonly SessionManagerInterface $sessions,
        private readonly PunchoutLogger $logger,
    ) {}

    public function handle(Request $request): Response
    {
        $rawXml = $request->getContent();

        try {
            return $this->process($rawXml);
        } catch (Throwable $exception) {
            // Last-resort net: this endpoint's whole contract is that
            // Coupa's server always gets well-formed cXML back, never an
            // HTML error page or a bare 500, whatever goes wrong. Every
            // expected failure is already handled in process() with its
            // own Status code; this only catches what nobody anticipated,
            // so it deliberately never calls back into $this->protocol,
            // that dependency may be exactly what just failed.
            try {
                Log::channel('punchout')->error('Unexpected failure handling PunchOutSetupRequest.', [
                    'error' => $exception->getMessage(),
                ]);

                $this->logger->logInbound(PunchoutMessageType::SetupRequest, $rawXml, httpStatus: 500, error: $exception->getMessage());
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

    private function process(string $rawXml): Response
    {
        try {
            $data = $this->protocol->parseSetupRequest($rawXml);
        } catch (MalformedCxmlException $exception) {
            $this->logger->logInbound(PunchoutMessageType::SetupRequest, $rawXml, httpStatus: 400, error: $exception->getMessage());

            return $this->xmlResponse(
                $this->protocol->buildSetupResponse(SetupResponseData::failure(400, 'Malformed request.')),
                400,
            );
        }

        try {
            $this->credentials->validate(CxmlHeaderData::fromSetupRequest($data));
        } catch (InvalidCredentialsException $exception) {
            $this->logger->logInbound(PunchoutMessageType::SetupRequest, $rawXml, httpStatus: 401, error: $exception->getMessage());

            return $this->xmlResponse(
                $this->protocol->buildSetupResponse(SetupResponseData::failure(401, 'Unauthorized.')),
                401,
            );
        }

        $session = $this->sessions->start($data);

        $this->logger->logInbound(PunchoutMessageType::SetupRequest, $rawXml, $session, 200);

        $startUrl = route('punchout.start', ['token' => $session->token]);
        $responseXml = $this->protocol->buildSetupResponse(SetupResponseData::success($startUrl));

        $this->logger->logOutbound(PunchoutMessageType::SetupResponse, $responseXml, $session, 200);

        return $this->xmlResponse($responseXml, 200);
    }

    private function xmlResponse(string $xml, int $status): Response
    {
        return response($xml, $status)->header('Content-Type', 'text/xml; charset=UTF-8');
    }
}
