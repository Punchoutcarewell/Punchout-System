<?php

declare(strict_types=1);

use App\Modules\Punchout\Cxml\SetupRequestParser;
use App\Modules\Punchout\Enums\PunchoutOperation;
use App\Modules\Punchout\Exceptions\MalformedCxmlException;

it('parses every field from the real Amazon sample PunchOutSetupRequest', function () {
    $data = (new SetupRequestParser)->parse(file_get_contents(dirname(__DIR__, 2).'/Fixtures/Cxml/setup_request.xml'));

    expect($data->fromDomain)->toBe('DUNS')
        ->and($data->fromIdentity)->toBe('COUPA1')
        ->and($data->toDomain)->toBe('DUNS')
        ->and($data->toIdentity)->toBe('079928354')
        ->and($data->senderDomain)->toBe('DUNS')
        ->and($data->senderIdentity)->toBe('COUPA1')
        ->and($data->sharedSecret)->toBe('ALD')
        ->and($data->operation)->toBe(PunchoutOperation::Create)
        ->and($data->buyerCookie)->toBe('99ea3c4c8cf9f6dc905a6b6772daa0d1')
        ->and($data->browserFormPostUrl)->toBe('https://mwilczek-demo.coupacloud.com/punchout/checkout?id=2')
        ->and($data->contactName)->toBe('maryanne.krzeminski@coupa.com')
        ->and($data->contactEmail)->toBe('maryanne.krzeminski@coupa.com')
        ->and($data->supplierSetupUrl)->toBe('https://uttest.free.beeceptor.com')
        ->and($data->extrinsic('FirstName'))->toBe('Mary Anne')
        ->and($data->extrinsic('LastName'))->toBe('Krzeminski')
        ->and($data->extrinsic('UserEmail'))->toBe('maryanne.krzeminski@coupa.com')
        ->and($data->extrinsic('BusinessUnit'))->toBe('COUPA')
        ->and($data->extrinsic('DoesNotExist'))->toBeNull();
});

it('rejects malformed XML', function () {
    (new SetupRequestParser)->parse('<not-even-xml');
})->throws(MalformedCxmlException::class);

it('rejects an empty body', function () {
    (new SetupRequestParser)->parse('');
})->throws(MalformedCxmlException::class);

it('rejects an unknown operation attribute', function () {
    $xml = str_replace('operation="create"', 'operation="destroy"', file_get_contents(dirname(__DIR__, 2).'/Fixtures/Cxml/setup_request.xml'));

    (new SetupRequestParser)->parse($xml);
})->throws(MalformedCxmlException::class);

it('rejects a payload missing a required element', function () {
    $xml = str_replace('<BuyerCookie>99ea3c4c8cf9f6dc905a6b6772daa0d1</BuyerCookie>', '', file_get_contents(dirname(__DIR__, 2).'/Fixtures/Cxml/setup_request.xml'));

    (new SetupRequestParser)->parse($xml);
})->throws(MalformedCxmlException::class);

it('never resolves an external entity (XXE)', function () {
    $malicious = <<<'XML'
    <?xml version="1.0" encoding="UTF-8"?>
    <!DOCTYPE cXML [
      <!ENTITY xxe SYSTEM "file:///etc/passwd">
    ]>
    <cXML xml:lang="en-US" payloadID="1" timestamp="2026-01-01T00:00:00-00:00">
      <Header>
        <From><Credential domain="DUNS"><Identity>COUPA1</Identity></Credential></From>
        <To><Credential domain="DUNS"><Identity>079928354</Identity></Credential></To>
        <Sender><Credential domain="DUNS"><Identity>COUPA1</Identity><SharedSecret>&xxe;</SharedSecret></Credential></Sender>
      </Header>
      <Request>
        <PunchOutSetupRequest operation="create">
          <BuyerCookie>test</BuyerCookie>
          <BrowserFormPost><URL>https://example.com/cart</URL></BrowserFormPost>
        </PunchOutSetupRequest>
      </Request>
    </cXML>
    XML;

    try {
        $data = (new SetupRequestParser)->parse($malicious);

        // Succeeding is fine as long as the entity was never substituted
        // with the target file's contents.
        expect($data->sharedSecret)->not->toContain('root:');
    } catch (MalformedCxmlException) {
        // Refusing to parse a payload with an unresolved external entity is
        // an equally safe outcome. What would be unsafe is /etc/passwd's
        // contents appearing anywhere in a successfully parsed result.
        expect(true)->toBeTrue();
    }
});
