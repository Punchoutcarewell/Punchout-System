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

it('reports where a raw unescaped ampersand broke the document', function () {
    $xml = "<cXML>\n<Name>Carewell Health & Medical</Name>\n</cXML>";

    try {
        XmlSecurity::loadSafely($xml);
        $this->fail('Expected MalformedCxmlException.');
    } catch (MalformedCxmlException $exception) {
        expect($exception->getMessage())->toContain('not well-formed XML')
            ->and($exception->getMessage())->toContain('line 2, column');
    }
});

it('never quotes payload content, which could be a secret, in its diagnostics', function () {
    $xml = '<cXML><SharedSecret>top&secretvalue;x</SharedSecret></cXML>';

    try {
        XmlSecurity::loadSafely($xml);
        $this->fail('Expected MalformedCxmlException.');
    } catch (MalformedCxmlException $exception) {
        expect($exception->getMessage())->not->toContain('secretvalue')
            ->and(json_encode($exception->context()))->not->toContain('secretvalue');
    }
});

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
