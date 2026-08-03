<?php

declare(strict_types=1);

namespace App\Modules\Punchout\Cxml;

use App\Modules\Punchout\Data\SetupResponseData;
use RuntimeException;

/**
 * Renders a PunchOutSetupResponse. A failure response is still valid cXML
 * carrying the appropriate Status code, never an HTML error page: Coupa
 * surfaces the Status text directly to the buyer.
 */
final class SetupResponseBuilder
{
    public function __construct(private readonly CxmlDocumentFactory $documents = new CxmlDocumentFactory) {}

    public function build(SetupResponseData $data): string
    {
        $document = $this->documents->newDocument();

        $cxml = $document->createElement('cXML');
        $cxml->setAttribute('xml:lang', 'en-US');
        $cxml->setAttribute('payloadID', $this->documents->payloadId());
        $cxml->setAttribute('timestamp', $this->documents->timestamp());
        $document->appendChild($cxml);

        $response = $document->createElement('Response');
        $cxml->appendChild($response);

        $status = $document->createElement('Status');
        $status->setAttribute('code', (string) $data->statusCode);
        $status->setAttribute('text', $data->statusText);
        $response->appendChild($status);

        if ($data->startUrl !== null) {
            $punchOutResponse = $document->createElement('PunchOutSetupResponse');
            $response->appendChild($punchOutResponse);

            $startPage = $document->createElement('StartPage');
            $punchOutResponse->appendChild($startPage);

            $this->documents->appendTextElement($document, $startPage, 'URL', $data->startUrl);
        }

        $xml = $document->saveXML();

        if ($xml === false) {
            throw new RuntimeException('Failed to serialize the PunchOutSetupResponse document.');
        }

        return $xml;
    }
}
