<?php

declare(strict_types=1);

namespace App\Modules\Punchout\Http\Controllers;

use App\Modules\Punchout\Contracts\SessionManagerInterface;
use App\Modules\Punchout\Cxml\CxmlProtocol;
use App\Modules\Punchout\Data\SetupResponseData;
use App\Modules\Punchout\Enums\PunchoutMessageType;
use App\Modules\Punchout\Exceptions\InvalidCredentialsException;
use App\Modules\Punchout\Exceptions\MalformedCxmlException;
use App\Modules\Punchout\Services\CredentialValidator;
use App\Modules\Punchout\Services\PunchoutLogger;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * POST /punchout/setup
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
        private readonly CxmlProtocol $protocol,
        private readonly CredentialValidator $credentials,
        private readonly SessionManagerInterface $sessions,
        private readonly PunchoutLogger $logger,
    ) {}

    public function handle(Request $request): Response
    {
        $rawXml = $request->getContent();

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
            $this->credentials->validate($data);
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
