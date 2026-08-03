<?php

declare(strict_types=1);

use App\Modules\Punchout\Enums\PunchoutMessageType;
use App\Modules\Punchout\Models\PunchoutLog;

it('accepts a well-formed OrderRequest and acknowledges it', function () {
    $xml = <<<'XML'
    <?xml version="1.0" encoding="UTF-8"?>
    <!DOCTYPE cXML SYSTEM "http://xml.cxml.org/schemas/cXML/1.2.014/cXML.dtd">
    <cXML xml:lang="en-US" payloadID="1@coupahost.com" timestamp="2026-08-10T10:00:00-05:00">
      <Header>
        <From><Credential domain="DUNS"><Identity>COUPA1</Identity></Credential></From>
        <To><Credential domain="DUNS"><Identity>079928354</Identity></Credential></To>
        <Sender><Credential domain="DUNS"><Identity>COUPA1</Identity></Credential></Sender>
      </Header>
      <Request deploymentMode="test">
        <OrderRequest>
          <OrderRequestHeader orderID="PO-1" orderDate="2026-08-10T10:00:00-05:00" type="new">
            <Total><Money currency="AUD">25.99</Money></Total>
          </OrderRequestHeader>
          <ItemOut lineNumber="1" quantity="1">
            <ItemID><SupplierPartID>CW-4021</SupplierPartID></ItemID>
            <ItemDetail>
              <UnitPrice><Money currency="AUD">25.99</Money></UnitPrice>
              <Description xml:lang="en-US">Foam Wound Dressing</Description>
              <UnitOfMeasure>BX</UnitOfMeasure>
            </ItemDetail>
          </ItemOut>
        </OrderRequest>
      </Request>
    </cXML>
    XML;

    $response = $this->call('POST', '/punchout/order', content: $xml, server: ['CONTENT_TYPE' => 'text/xml']);

    $response->assertStatus(200);
    expect($response->getContent())->toContain('code="200"');
});

it('persists the raw payload before parsing is attempted, even when parsing fails', function () {
    $response = $this->call('POST', '/punchout/order', content: '<not-xml', server: ['CONTENT_TYPE' => 'text/xml']);

    $response->assertStatus(400);
    expect($response->getContent())->toContain('code="400"')
        ->and($response->getContent())->not->toContain('<html');

    $log = PunchoutLog::query()->where('message_type', PunchoutMessageType::OrderRequest->value)->first();

    expect($log)->not->toBeNull()
        ->and($log->raw_payload)->toBe('<not-xml');
});
