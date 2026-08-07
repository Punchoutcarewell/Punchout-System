<?php

declare(strict_types=1);

namespace App\Modules\Punchout\Cxml;

use App\Modules\Punchout\Data\CartLineSnapshot;
use App\Modules\Punchout\Data\OrderMessageData;
use App\Shared\ValueObjects\Money;
use DOMDocument;
use DOMElement;
use RuntimeException;

/**
 * Renders the outbound PunchOutOrderMessage sent when the buyer transfers
 * their cart back to Coupa. Element names and structure match the sample
 * payload in Amazon's supplier questionnaire, used as the golden fixture
 * in this module's tests.
 *
 * The single rule that matters most here: BuyerCookie is written exactly
 * as it was captured at setup time, never regenerated or reformatted. A
 * mismatch causes Coupa to discard the cart silently, with nothing visible
 * to the buyer to explain why.
 */
final class OrderMessageBuilder
{
    public function __construct(private readonly CxmlDocumentFactory $documents = new CxmlDocumentFactory) {}

    public function build(OrderMessageData $data): string
    {
        $document = $this->documents->newDocument();

        $cxml = $document->createElement('cXML');
        $cxml->setAttribute('payloadID', $this->documents->payloadId());
        $cxml->setAttribute('xml:lang', 'en-US');
        $cxml->setAttribute('timestamp', $this->documents->timestamp());
        $document->appendChild($cxml);

        $cxml->appendChild($this->buildHeader($document, $data));

        $message = $document->createElement('Message');
        $message->setAttribute('deploymentMode', $data->deploymentMode);
        $cxml->appendChild($message);

        $orderMessage = $document->createElement('PunchOutOrderMessage');
        $message->appendChild($orderMessage);

        $this->documents->appendTextElement($document, $orderMessage, 'BuyerCookie', $data->buyerCookie);

        $orderMessage->appendChild($this->buildOrderMessageHeader($document, $data));

        foreach ($data->cart->lines as $line) {
            $orderMessage->appendChild($this->buildItemIn($document, $line));
        }

        $xml = $document->saveXML();

        if ($xml === false) {
            throw new RuntimeException('Failed to serialize the PunchOutOrderMessage document.');
        }

        return $xml;
    }

    private function buildHeader(DOMDocument $document, OrderMessageData $data): DOMElement
    {
        $header = $document->createElement('Header');

        $from = $document->createElement('From');
        $from->appendChild($this->buildCredential($document, $data->fromDomain, $data->fromIdentity));
        $header->appendChild($from);

        $to = $document->createElement('To');
        $to->appendChild($this->buildCredential($document, $data->toDomain, $data->toIdentity));
        $header->appendChild($to);

        $sender = $document->createElement('Sender');
        $sender->appendChild($this->buildCredential($document, $data->senderDomain, $data->senderIdentity));
        $this->documents->appendTextElement($document, $sender, 'UserAgent', 'Carewell PunchOut 1.0');
        $header->appendChild($sender);

        return $header;
    }

    private function buildCredential(DOMDocument $document, string $domain, string $identity): DOMElement
    {
        $credential = $document->createElement('Credential');
        $credential->setAttribute('domain', $domain);
        $this->documents->appendTextElement($document, $credential, 'Identity', $identity);

        return $credential;
    }

    private function buildOrderMessageHeader(DOMDocument $document, OrderMessageData $data): DOMElement
    {
        $header = $document->createElement('PunchOutOrderMessageHeader');
        $header->setAttribute('operationAllowed', $data->operationAllowed);
        $header->setAttribute('quoteStatus', $data->quoteStatus);

        $total = $document->createElement('Total');
        $header->appendChild($total);
        $this->buildMoney($document, $total, $data->cart->total());

        return $header;
    }

    private function buildItemIn(DOMDocument $document, CartLineSnapshot $line): DOMElement
    {
        $itemIn = $document->createElement('ItemIn');
        $itemIn->setAttribute('quantity', (string) $line->quantity);

        $itemId = $document->createElement('ItemID');
        $itemIn->appendChild($itemId);
        $this->documents->appendTextElement($document, $itemId, 'SupplierPartID', $line->supplierPartId);

        if ($line->supplierPartAuxiliaryId !== null) {
            $this->documents->appendTextElement($document, $itemId, 'SupplierPartAuxiliaryID', $line->supplierPartAuxiliaryId);
        }

        $itemDetail = $document->createElement('ItemDetail');
        $itemIn->appendChild($itemDetail);

        $unitPrice = $document->createElement('UnitPrice');
        $itemDetail->appendChild($unitPrice);
        $this->buildMoney($document, $unitPrice, $line->unitPrice);

        $description = $this->documents->appendTextElement($document, $itemDetail, 'Description', $line->description);
        $description->setAttribute('xml:lang', 'en-US');

        $this->documents->appendTextElement($document, $itemDetail, 'UnitOfMeasure', $line->unitOfMeasure);

        $classification = $this->documents->appendTextElement($document, $itemDetail, 'Classification', $line->unspscCode->value());
        $classification->setAttribute('domain', 'UNSPSC');

        if ($line->manufacturerPartId !== null) {
            $this->documents->appendTextElement($document, $itemDetail, 'ManufacturerPartID', $line->manufacturerPartId);
        }

        if ($line->manufacturerName !== null) {
            $manufacturerName = $this->documents->appendTextElement($document, $itemDetail, 'ManufacturerName', $line->manufacturerName);
            $manufacturerName->setAttribute('xml:lang', 'en');
        }

        $this->documents->appendTextElement($document, $itemDetail, 'LeadTime', (string) $line->leadTimeDays);

        return $itemIn;
    }

    private function buildMoney(DOMDocument $document, DOMElement $parent, Money $money): void
    {
        $element = $this->documents->appendTextElement($document, $parent, 'Money', $money->toDecimalString());
        $element->setAttribute('currency', $money->currency());
    }
}
