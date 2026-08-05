<?php

declare(strict_types=1);

namespace App\Modules\Punchout\Cxml;

use DateTimeImmutable;
use DOMDocument;
use DOMElement;
use DOMImplementation;
use Illuminate\Support\Str;

/**
 * Builds a fresh cXML document with the standard DOCTYPE and a payloadID
 * and timestamp, the boilerplate every outbound payload in this module
 * needs, so the three builder classes do not each reimplement it slightly
 * differently.
 */
final class CxmlDocumentFactory
{
    private const DTD_VERSION = '1.2.014';

    public function newDocument(): DOMDocument
    {
        $implementation = new DOMImplementation;

        $doctype = $implementation->createDocumentType(
            'cXML',
            '',
            'http://xml.cxml.org/schemas/cXML/'.self::DTD_VERSION.'/cXML.dtd',
        );

        $document = $implementation->createDocument(null, '', $doctype);
        $document->encoding = 'UTF-8';
        $document->xmlVersion = '1.0';
        $document->formatOutput = false;

        return $document;
    }

    public function payloadId(): string
    {
        // uniqid() is time-based, not a real uniqueness guarantee, two
        // payloads built within the same microsecond on the same PHP-FPM
        // worker (a burst of concurrent transfers) could theoretically
        // collide. A UUID is a genuine uniqueness guarantee.
        return Str::uuid()->toString().'@carewellgroup.com.au';
    }

    public function timestamp(): string
    {
        return (new DateTimeImmutable)->format(DATE_ATOM);
    }

    /**
     * A text-only child element, appended to $parent. Always goes through
     * DOMText so entity escaping on serialization is guaranteed rather
     * than relying on the ambiguous two-argument form of createElement().
     */
    public function appendTextElement(DOMDocument $document, DOMElement $parent, string $name, string $text): DOMElement
    {
        $element = $document->createElement($name);
        $element->appendChild($document->createTextNode($text));
        $parent->appendChild($element);

        return $element;
    }
}
