<?php

declare(strict_types=1);

namespace App\Modules\Punchout\Cxml;

use App\Modules\Punchout\Data\OrderRequestData;
use App\Modules\Punchout\Data\OrderRequestLineData;
use App\Modules\Punchout\Exceptions\MalformedCxmlException;
use App\Shared\ValueObjects\Money;
use DateTimeImmutable;
use DOMElement;
use Exception;

/**
 * Turns a raw OrderRequest cXML payload, the purchase order Coupa sends
 * once the buyer submits their requisition, into a typed OrderRequestData.
 *
 * Unlike SetupRequestParser and OrderMessageBuilder, no sample OrderRequest
 * payload was provided in Amazon's supplier questionnaire or the GPCS
 * process guide: PO transmission type (CSP, email, or cXML) is still an
 * open question for this project. This parser is built against the
 * standard cXML OrderRequest structure documented at cxml.org, but it has
 * not been validated against a real Coupa-issued OrderRequest. That
 * validation needs to happen against an actual specimen once GPCS confirms
 * cXML as the PO transmission channel, the same specimen-review step the
 * roadmap already calls for before credentials are submitted.
 */
final class OrderRequestParser
{
    public function parse(string $rawXml): OrderRequestData
    {
        $reader = new XPathReader(XmlSecurity::loadSafely($rawXml));

        $cxmlHeader = $reader->requireElement('/cXML/Header', 'Header');
        $credentials = (new CxmlHeaderParser)->parse($reader, $cxmlHeader);

        $orderRequest = $reader->requireElement('/cXML/Request/OrderRequest', 'OrderRequest');
        $header = $reader->requireElement('OrderRequestHeader', 'OrderRequestHeader', $orderRequest);

        $poNumber = $header->getAttribute('orderID');

        if ($poNumber === '') {
            throw MalformedCxmlException::withContext('OrderRequestHeader is missing the orderID attribute.');
        }

        $orderDate = $this->parseDate($header->getAttribute('orderDate'));

        $totalMoney = $reader->requireElement('Total/Money', 'OrderRequestHeader/Total/Money', $header);
        $total = Money::fromDecimal(trim($totalMoney->textContent), $totalMoney->getAttribute('currency'));

        $buyerReference = $reader->text('Extrinsic[@name="buyerReference"]', $header);

        return new OrderRequestData(
            fromDomain: $credentials->fromDomain,
            fromIdentity: $credentials->fromIdentity,
            toDomain: $credentials->toDomain,
            toIdentity: $credentials->toIdentity,
            senderDomain: $credentials->senderDomain,
            senderIdentity: $credentials->senderIdentity,
            sharedSecret: $credentials->sharedSecret,
            poNumber: $poNumber,
            orderDate: $orderDate,
            total: $total,
            buyerReference: $buyerReference,
            lines: $this->lines($reader, $orderRequest),
        );
    }

    /**
     * @return OrderRequestLineData[]
     */
    private function lines(XPathReader $reader, DOMElement $orderRequest): array
    {
        $lines = [];

        foreach ($reader->all('ItemOut', $orderRequest) as $itemOut) {
            if (! $itemOut instanceof DOMElement) {
                continue;
            }

            $supplierPartId = $reader->requireText('ItemID/SupplierPartID', 'ItemOut/ItemID/SupplierPartID', $itemOut);
            $unitOfMeasure = $reader->requireText('ItemDetail/UnitOfMeasure', 'ItemOut/ItemDetail/UnitOfMeasure', $itemOut);
            $description = $reader->text('ItemDetail/Description', $itemOut) ?? '';

            $priceElement = $reader->requireElement('ItemDetail/UnitPrice/Money', 'ItemOut/ItemDetail/UnitPrice/Money', $itemOut);
            $unitPrice = Money::fromDecimal(trim($priceElement->textContent), $priceElement->getAttribute('currency'));

            $lines[] = new OrderRequestLineData(
                lineNumber: (int) $itemOut->getAttribute('lineNumber'),
                supplierPartId: $supplierPartId,
                quantity: (int) $itemOut->getAttribute('quantity'),
                unitPrice: $unitPrice,
                unitOfMeasure: $unitOfMeasure,
                description: $description,
            );
        }

        if ($lines === []) {
            throw MalformedCxmlException::withContext('OrderRequest contains no ItemOut lines.');
        }

        return $lines;
    }

    private function parseDate(string $value): DateTimeImmutable
    {
        if ($value === '') {
            return new DateTimeImmutable;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Exception) {
            throw MalformedCxmlException::withContext("Invalid orderDate [{$value}].", ['orderDate' => $value]);
        }
    }
}
