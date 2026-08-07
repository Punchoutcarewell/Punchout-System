<?php

declare(strict_types=1);

namespace App\Modules\Punchout\Cxml;

use App\Modules\Punchout\Data\CxmlHeaderData;
use DOMNode;

/**
 * Parses the From/To/Sender/SharedSecret credentials out of a cXML
 * message's <Header>, shared by every inbound parser in this module
 * rather than each one re-implementing the same DOM traversal. This is
 * what CredentialValidator authenticates against, whichever message type
 * carried it.
 */
final class CxmlHeaderParser
{
    public function parse(XPathReader $reader, DOMNode $header): CxmlHeaderData
    {
        return new CxmlHeaderData(
            fromDomain: $this->credentialDomain($reader, $header, 'From'),
            fromIdentity: $this->credentialIdentity($reader, $header, 'From'),
            toDomain: $this->credentialDomain($reader, $header, 'To'),
            toIdentity: $this->credentialIdentity($reader, $header, 'To'),
            senderDomain: $this->credentialDomain($reader, $header, 'Sender'),
            senderIdentity: $this->credentialIdentity($reader, $header, 'Sender'),
            sharedSecret: $reader->text('Sender/Credential/SharedSecret', $header) ?? '',
        );
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
