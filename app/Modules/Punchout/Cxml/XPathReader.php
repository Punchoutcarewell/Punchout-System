<?php

declare(strict_types=1);

namespace App\Modules\Punchout\Cxml;

use App\Modules\Punchout\Exceptions\MalformedCxmlException;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMNodeList;
use DOMXPath;

/**
 * A small convenience wrapper around DOMXPath shared by every cXML parser
 * in this module, so "find this element, or fail with a clear parsing
 * error naming what was expected" is written once instead of once per
 * parser.
 */
final class XPathReader
{
    private readonly DOMXPath $xpath;

    public function __construct(DOMDocument $document)
    {
        $this->xpath = new DOMXPath($document);
    }

    public function element(string $query, ?DOMNode $context = null): ?DOMElement
    {
        $node = $this->all($query, $context)->item(0);

        return $node instanceof DOMElement ? $node : null;
    }

    public function requireElement(string $query, string $label, ?DOMNode $context = null): DOMElement
    {
        $element = $this->element($query, $context);

        if ($element === null) {
            throw MalformedCxmlException::withContext("Missing required element [{$label}].", ['query' => $query]);
        }

        return $element;
    }

    public function text(string $query, ?DOMNode $context = null): ?string
    {
        $node = $this->all($query, $context)->item(0);

        if ($node === null) {
            return null;
        }

        $value = trim($node->textContent);

        return $value === '' ? null : $value;
    }

    public function requireText(string $query, string $label, ?DOMNode $context = null): string
    {
        $value = $this->text($query, $context);

        if ($value === null) {
            throw MalformedCxmlException::withContext("Missing required element [{$label}].", ['query' => $query]);
        }

        return $value;
    }

    /**
     * @return DOMNodeList<DOMNode>
     */
    public function all(string $query, ?DOMNode $context = null): DOMNodeList
    {
        $result = $context !== null ? $this->xpath->query($query, $context) : $this->xpath->query($query);

        if ($result === false) {
            throw MalformedCxmlException::withContext("Invalid XPath query [{$query}].", ['query' => $query]);
        }

        return $result;
    }
}
