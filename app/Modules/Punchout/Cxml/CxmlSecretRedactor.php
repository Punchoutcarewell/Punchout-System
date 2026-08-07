<?php

declare(strict_types=1);

namespace App\Modules\Punchout\Cxml;

use App\Modules\Punchout\Exceptions\MalformedCxmlException;
use DOMElement;
use DOMXPath;
use Throwable;

/**
 * Redacts every SharedSecret and Password element's content, wherever it
 * appears in a cXML document, before the payload is allowed to reach any
 * form of storage or logging. A pure utility, not tied to the Punchout
 * module's own models: Orders depends on this directly (a plain class,
 * not an Eloquent model) for the same reason, purchase_orders.raw_payload
 * needs the exact same guarantee once an OrderRequest carries a
 * SharedSecret too.
 *
 * Deliberately not a regex: a regex anchored on "not a < character"
 * cannot match a CDATA-wrapped secret (<SharedSecret><![CDATA[...]]>),
 * which is well-formed, spec-legal cXML, not an exotic edge case. A
 * payload that fails to parse at all is not redacted with a regex
 * either: a payload this module could not fully understand is exactly
 * the case where guessing wrong and leaking a secret is most likely, so
 * it is replaced entirely with a hash, preserving enough to spot
 * duplicate/replayed junk traffic without ever risking plaintext.
 */
final class CxmlSecretRedactor
{
    public function redact(string $payload): string
    {
        try {
            $document = XmlSecurity::loadSafely($payload);
        } catch (MalformedCxmlException) {
            return $this->unparseablePlaceholder($payload);
        }

        $xpath = new DOMXPath($document);
        $nodes = $xpath->query('//SharedSecret | //Password');

        if ($nodes === false) {
            return $this->unparseablePlaceholder($payload);
        }

        foreach ($nodes as $node) {
            if ($node instanceof DOMElement) {
                $node->textContent = '[REDACTED]';
            }
        }

        try {
            $serialized = $document->saveXML();
        } catch (Throwable) {
            $serialized = false;
        }

        return $serialized !== false ? $serialized : $this->unparseablePlaceholder($payload);
    }

    private function unparseablePlaceholder(string $payload): string
    {
        return '[unparseable payload, not stored: sha256:'.hash('sha256', $payload).', '.strlen($payload).' bytes]';
    }
}
