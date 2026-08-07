<?php

declare(strict_types=1);

namespace App\Modules\Punchout\Cxml;

use App\Modules\Punchout\Exceptions\MalformedCxmlException;
use DOMDocument;
use LibXMLError;

/**
 * Every inbound XML parse in this module goes through here first. XXE is
 * the obvious attack on a public endpoint that accepts arbitrary XML, so
 * external entity and network access are refused explicitly rather than
 * relied on as a libxml default that could change.
 *
 * A real cXML payload legitimately carries a DOCTYPE declaration pointing
 * at the public cXML DTD (see the sample payloads in this module's
 * fixtures), so DOCTYPE itself is not rejected. What matters is that it is
 * never resolved: LIBXML_NONET stops any network fetch of that external
 * DTD, and resolveExternals/substituteEntities being false stops entity
 * substitution, which together close the XXE path without breaking
 * parsing of a legitimate payload.
 */
final class XmlSecurity
{
    public static function loadSafely(string $rawXml): DOMDocument
    {
        if (trim($rawXml) === '') {
            throw MalformedCxmlException::withContext('The request body is empty.');
        }

        $document = new DOMDocument;
        $document->resolveExternals = false;
        $document->substituteEntities = false;

        $previousSetting = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $loaded = $document->loadXML($rawXml, LIBXML_NONET | LIBXML_NOCDATA);

        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previousSetting);

        if (! $loaded) {
            $messages = array_map(
                static fn (LibXMLError $error): string => trim($error->message),
                $errors,
            );

            throw MalformedCxmlException::withContext(
                'The request body is not well-formed XML.',
                ['libxml_errors' => $messages],
            );
        }

        return $document;
    }
}
