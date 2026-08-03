<?php

declare(strict_types=1);

use App\Modules\Orders\Jobs\SendPurchaseOrderNotification;
use App\Modules\Orders\Models\PurchaseOrder;
use App\Modules\Punchout\Contracts\PunchoutProtocolInterface;
use App\Modules\Punchout\Enums\PunchoutMessageType;
use App\Modules\Punchout\Models\PunchoutLog;
use Illuminate\Support\Facades\Queue;

it('accepts a well-formed OrderRequest, creates a PurchaseOrder, and acknowledges it', function () {
    Queue::fake();

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

    $purchaseOrder = PurchaseOrder::query()->where('po_number', 'PO-1')->first();

    expect($purchaseOrder)->not->toBeNull()
        ->and($purchaseOrder->total)->toBe('25.99')
        ->and($purchaseOrder->raw_payload)->toBe($xml)
        ->and($purchaseOrder->lines)->toHaveCount(1);

    Queue::assertPushed(SendPurchaseOrderNotification::class);
});

it('does not create a second PurchaseOrder for a resent po_number', function () {
    Queue::fake();

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
          <OrderRequestHeader orderID="PO-DUPLICATE" orderDate="2026-08-10T10:00:00-05:00" type="new">
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

    $this->call('POST', '/punchout/order', content: $xml, server: ['CONTENT_TYPE' => 'text/xml']);
    $this->call('POST', '/punchout/order', content: $xml, server: ['CONTENT_TYPE' => 'text/xml']);

    expect(PurchaseOrder::query()->where('po_number', 'PO-DUPLICATE')->count())->toBe(1);
    Queue::assertPushed(SendPurchaseOrderNotification::class, 1);
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

it('still returns a well-formed cXML error, never a bare 500, when something entirely unexpected fails', function () {
    $this->mock(PunchoutProtocolInterface::class, function ($mock): void {
        $mock->shouldReceive('parseOrderRequest')->andThrow(new RuntimeException('unexpected native failure'));
    });

    $response = $this->call('POST', '/punchout/order', content: '<cXML></cXML>', server: ['CONTENT_TYPE' => 'text/xml']);

    $response->assertStatus(500);
    expect($response->getContent())->toContain('<?xml')
        ->and($response->getContent())->toContain('code="500"')
        ->and($response->getContent())->not->toContain('<html');
});
