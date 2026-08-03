<?php

declare(strict_types=1);

namespace App\Modules\Punchout\Cxml;

use App\Modules\Punchout\Data\OrderResponseData;
use RuntimeException;

/**
 * Renders the cXML Response acknowledging a received OrderRequest.
 */
final class OrderResponseBuilder
{
    public function __construct(private readonly CxmlDocumentFactory $documents = new CxmlDocumentFactory) {}

    public function build(OrderResponseData $data): string
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

        $xml = $document->saveXML();

        if ($xml === false) {
            throw new RuntimeException('Failed to serialize the OrderRequest Response document.');
        }

        return $xml;
    }
}
