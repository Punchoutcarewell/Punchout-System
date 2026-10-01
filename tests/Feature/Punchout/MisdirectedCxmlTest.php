<?php

declare(strict_types=1);

use App\Modules\Orders\Jobs\SendPurchaseOrderNotification;
use App\Modules\Orders\Models\PurchaseOrder;
use App\Modules\Punchout\Enums\PunchoutMessageType;
use App\Modules\Punchout\Models\PunchoutLog;
use App\Modules\Punchout\Models\PunchoutSession;
use Illuminate\Support\Facades\Queue;

function setupXml(): string
{
    return (string) file_get_contents(base_path('tests/Fixtures/Cxml/setup_request.xml'));
}

function orderXml(): string
{
    return <<<'XML'
    <?xml version="1.0" encoding="UTF-8"?>
    <cXML xml:lang="en-US" payloadID="1@coupahost.com" timestamp="2026-08-10T10:00:00-05:00">
      <Header>
        <From><Credential domain="DUNS"><Identity>COUPA1</Identity></Credential></From>
        <To><Credential domain="DUNS"><Identity>079928354</Identity></Credential></To>
        <Sender><Credential domain="DUNS"><Identity>COUPA1</Identity><SharedSecret>ALD</SharedSecret></Credential></Sender>
      </Header>
      <Request deploymentMode="test">
        <OrderRequest>
          <OrderRequestHeader orderID="PO-MIS-1" orderDate="2026-08-10T10:00:00-05:00" type="new">
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
}

it('forwards a setup request sent to a browser page to the real setup endpoint', function (string $path) {
    createTestPunchoutCredential('ALD');

    $response = $this->call('POST', $path, content: setupXml(), server: ['CONTENT_TYPE' => 'text/xml']);

    $response->assertStatus(200);
    expect($response->getContent())->toContain('<StartPage>')
        ->and($response->headers->get('Content-Type'))->toContain('text/xml')
        ->and(PunchoutSession::query()->count())->toBe(1);
})->with(['/storefront', '/', '/storefront/catalog', '/api/punchout/setup/some-secret', '/api/punchout/setup/']);

it('still validates credentials on a forwarded setup request', function () {
    createTestPunchoutCredential('RIGHT');

    $response = $this->call('POST', '/storefront', content: setupXml(), server: ['CONTENT_TYPE' => 'text/xml']);

    $response->assertStatus(401);
    expect($response->getContent())->toContain('code="401"')
        ->and(PunchoutSession::query()->count())->toBe(0);
});

it('forwards an order request sent to an admin page to the real order endpoint', function () {
    Queue::fake();
    createTestPunchoutCredential('ALD');

    $response = $this->call('POST', '/admin/punchout-preview/complete', content: orderXml(), server: ['CONTENT_TYPE' => 'application/xml']);

    $response->assertStatus(200);
    expect($response->getContent())->toContain('code="200"')
        ->and($response->headers->get('Content-Type'))->toContain('text/xml')
        ->and(PurchaseOrder::query()->where('po_number', 'PO-MIS-1')->exists())->toBeTrue();

    Queue::assertPushed(SendPurchaseOrderNotification::class);
});

it('detects cXML by its body when the content type is wrong', function () {
    createTestPunchoutCredential('ALD');

    $response = $this->call('POST', '/storefront', content: setupXml(), server: ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

    $response->assertStatus(200);
    expect($response->getContent())->toContain('<StartPage>');
});

it('answers unrecognised cXML sent to a wrong URL with a cXML fault, never HTML, and logs it', function () {
    $xml = '<?xml version="1.0"?><cXML><Header/><Request><ProfileRequest/></Request></cXML>';

    $response = $this->call('POST', '/storefront', content: $xml, server: ['CONTENT_TYPE' => 'text/xml']);

    $response->assertStatus(400);
    expect($response->headers->get('Content-Type'))->toContain('text/xml')
        ->and($response->getContent())->toContain('code="400"')
        ->and($response->getContent())->toContain('/api/punchout/setup');

    expect(simplexml_load_string($response->getContent()))->not->toBeFalse();

    $log = PunchoutLog::query()->latest('id')->first();
    expect($log->message_type)->toBe(PunchoutMessageType::Unrecognised)
        ->and($log->http_status)->toBe(400)
        ->and($log->error)->toContain('POST /storefront');
});

it('answers an XML body that is not well formed with cXML as well', function () {
    $response = $this->call('POST', '/admin/login', content: '<cXML><oops & broken', server: ['CONTENT_TYPE' => 'text/xml']);

    $response->assertStatus(400);
    expect($response->getContent())->toStartWith('<?xml')
        ->and($response->getContent())->not->toContain('<html');
});

it('leaves browser requests alone', function () {
    $this->get('/storefront')->assertRedirect();

    $html = $this->get('/storefront/no-token');
    $html->assertOk();
    expect($html->headers->get('Content-Type'))->toContain('text/html');

    $this->post('/storefront', ['a' => 'b'])->assertStatus(405);
});

it('does not touch the real endpoints', function () {
    createTestPunchoutCredential('ALD');

    $this->call('POST', '/api/punchout/setup', content: setupXml(), server: ['CONTENT_TYPE' => 'text/xml'])->assertStatus(200);

    expect(PunchoutLog::query()->where('message_type', PunchoutMessageType::Unrecognised)->count())->toBe(0);
});

it('stops recording unrecognised requests once the rate limit is hit, but still answers cXML', function () {
    $xml = '<cXML><Header/><Request><Other/></Request></cXML>';

    foreach (range(1, 30) as $_) {
        $this->call('POST', '/storefront', content: $xml, server: ['CONTENT_TYPE' => 'text/xml'])->assertStatus(400);
    }

    $response = $this->call('POST', '/storefront', content: $xml, server: ['CONTENT_TYPE' => 'text/xml']);

    $response->assertStatus(429);
    expect($response->getContent())->toContain('code="429"')
        ->and(PunchoutLog::query()->count())->toBe(30);
});
