<?php

declare(strict_types=1);

use App\Modules\Punchout\Cxml\XmlSecurity;
use App\Modules\Punchout\Exceptions\MalformedCxmlException;

it('loads a well-formed document with a real cXML DOCTYPE', function () {
    $document = XmlSecurity::loadSafely(
        (string) file_get_contents(dirname(__DIR__, 2).'/Fixtures/Cxml/setup_request.xml'),
    );

    expect($document->documentElement?->tagName)->toBe('cXML');
});

it('rejects an empty body', function () {
    XmlSecurity::loadSafely('');
})->throws(MalformedCxmlException::class);

it('rejects XML that is not well-formed', function () {
    XmlSecurity::loadSafely('<cXML><Unclosed></cXML>');
})->throws(MalformedCxmlException::class);

it('never expands a local-file external entity', function () {
    $malicious = <<<'XML'
    <?xml version="1.0" encoding="UTF-8"?>
    <!DOCTYPE cXML [
      <!ENTITY xxe SYSTEM "file:///etc/passwd">
    ]>
    <cXML><Probe>&xxe;</Probe></cXML>
    XML;

    try {
        $document = XmlSecurity::loadSafely($malicious);
        $text = $document->getElementsByTagName('Probe')->item(0)?->textContent ?? '';

        expect($text)->not->toContain('root:');
    } catch (MalformedCxmlException) {
        expect(true)->toBeTrue();
    }
});
