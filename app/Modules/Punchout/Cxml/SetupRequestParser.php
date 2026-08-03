<?php

declare(strict_types=1);

namespace App\Modules\Punchout\Cxml;

use App\Modules\Punchout\Data\SetupRequestData;
use App\Modules\Punchout\Enums\PunchoutOperation;
use App\Modules\Punchout\Exceptions\MalformedCxmlException;
use DOMElement;
use DOMNode;

/**
 * Turns a raw PunchOutSetupRequest cXML payload into a typed
 * SetupRequestData. Element names and structure are taken directly from
 * the sample payload in Amazon's own supplier questionnaire, which is used
 * as the golden fixture in this module's tests.
 */
final class SetupRequestParser
{
    public function parse(string $rawXml): SetupRequestData
    {
        $reader = new XPathReader(XmlSecurity::loadSafely($rawXml));

        $header = $reader->requireElement('/cXML/Header', 'Header');
        $request = $reader->requireElement('/cXML/Request/PunchOutSetupRequest', 'PunchOutSetupRequest');

        $operationAttribute = $request->getAttribute('operation');
        $operation = PunchoutOperation::tryFrom($operationAttribute);

        if ($operation === null) {
            throw MalformedCxmlException::withContext(
                "Unknown PunchOutSetupRequest operation [{$operationAttribute}].",
                ['operation' => $operationAttribute],
            );
        }

        return new SetupRequestData(
            fromDomain: $this->credentialDomain($reader, $header, 'From'),
            fromIdentity: $this->credentialIdentity($reader, $header, 'From'),
            toDomain: $this->credentialDomain($reader, $header, 'To'),
            toIdentity: $this->credentialIdentity($reader, $header, 'To'),
            senderDomain: $this->credentialDomain($reader, $header, 'Sender'),
            senderIdentity: $this->credentialIdentity($reader, $header, 'Sender'),
            sharedSecret: $reader->text('Sender/Credential/SharedSecret', $header) ?? '',
            operation: $operation,
            buyerCookie: $reader->requireText('BuyerCookie', 'BuyerCookie', $request),
            browserFormPostUrl: $reader->requireText('BrowserFormPost/URL', 'BrowserFormPost/URL', $request),
            extrinsics: $this->extrinsics($reader, $request),
            contactName: $reader->text('Contact/Name', $request),
            contactEmail: $reader->text('Contact/Email', $request),
            supplierSetupUrl: $reader->text('SupplierSetup/URL', $request),
        );
    }

    /**
     * @return array<string, string>
     */
    private function extrinsics(XPathReader $reader, DOMNode $request): array
    {
        $extrinsics = [];

        foreach ($reader->all('Extrinsic', $request) as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $name = $node->getAttribute('name');

            if ($name !== '') {
                $extrinsics[$name] = trim($node->textContent);
            }
        }

        return $extrinsics;
    }

    private function credentialDomain(XPathReader $reader, DOMNode $header, string $section): string
    {
        $credential = $reader->requireElement("{$section}/Credential", "Header/{$section}/Credential", $header);

        return $credential->getAttribute('domain');
    }

    private function credentialIdentity(XPathReader $reader, DOMNode $header, string $section): string
    {
        return $reader->requireText("{$section}/Credential/Identity", "Header/{$section}/Credential/Identity", $header);
    }
}
