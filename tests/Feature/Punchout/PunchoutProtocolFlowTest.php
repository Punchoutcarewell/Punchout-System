<?php

declare(strict_types=1);

use App\Modules\Orders\Models\PurchaseOrder;
use App\Modules\Punchout\Models\PunchoutLog;
use App\Modules\Punchout\Models\PunchoutSession;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

/*
| End-to-end checks of the standard cXML PunchOut contract any buyer
| (Coupa, Amazon, ...) relies on: which URL takes which message, what
| comes back, and that no shared secret ever reaches a log or a response.
*/

const FLOW_TEST_SECRET = 'Flow-Test-Secret-9f3a';

function flowTestLogFile(): string
{
    $path = storage_path('logs/punchout-flow-test.log');
    @unlink($path);
    config(['logging.channels.punchout' => ['driver' => 'single', 'path' => $path]]);

    return $path;
}

function flowTestSetupRequest(string $secret = FLOW_TEST_SECRET): string
{
    $xml = (string) file_get_contents(base_path('tests/Fixtures/Cxml/setup_request.xml'));

    return str_replace('<SharedSecret>ALD</SharedSecret>', "<SharedSecret>{$secret}</SharedSecret>", $xml);
}

function flowTestOrderRequest(string $secret = FLOW_TEST_SECRET, string $description = 'Foam Wound Dressing'): string
{
    return <<<XML
    <?xml version="1.0" encoding="UTF-8"?>
    <!DOCTYPE cXML SYSTEM "http://xml.cxml.org/schemas/cXML/1.2.014/cXML.dtd">
    <cXML xml:lang="en-US" payloadID="flow-1@buyer.example" timestamp="2026-08-10T10:00:00-05:00">
      <Header>
        <From><Credential domain="DUNS"><Identity>COUPA1</Identity></Credential></From>
        <To><Credential domain="DUNS"><Identity>079928354</Identity></Credential></To>
        <Sender><Credential domain="DUNS"><Identity>COUPA1</Identity><SharedSecret>{$secret}</SharedSecret></Credential></Sender>
      </Header>
      <Request deploymentMode="test">
        <OrderRequest>
          <OrderRequestHeader orderID="PO-FLOW-1" orderDate="2026-08-10T10:00:00-05:00" type="new">
            <Total><Money currency="AUD">25.99</Money></Total>
          </OrderRequestHeader>
          <ItemOut lineNumber="1" quantity="1">
            <ItemID><SupplierPartID>CW-4021</SupplierPartID></ItemID>
            <ItemDetail>
              <UnitPrice><Money currency="AUD">25.99</Money></UnitPrice>
              <Description xml:lang="en-US">{$description}</Description>
              <UnitOfMeasure>BX</UnitOfMeasure>
            </ItemDetail>
          </ItemOut>
        </OrderRequest>
      </Request>
    </cXML>
    XML;
}

function expectNoSecretLeaked(string $responseBody, string $logFile, string $secret = FLOW_TEST_SECRET): void
{
    expect($responseBody)->not->toContain($secret);

    foreach (PunchoutLog::query()->get() as $log) {
        expect((string) $log->raw_payload)->not->toContain($secret)
            ->and((string) $log->error)->not->toContain($secret);
    }

    expect(is_file($logFile) ? (string) file_get_contents($logFile) : '')->not->toContain($secret);
}

function expectControlledCxmlFailure(string $body): void
{
    expect($body)->toStartWith('<?xml')
        ->and($body)->not->toContain('<html')
        ->and($body)->not->toContain('Stack trace')
        ->and($body)->not->toContain('vendor/laravel')
        ->and($body)->not->toContain('Exception');
}

it('runs the full PunchOutSetupRequest -> StartPage -> storefront handshake', function () {
    createTestPunchoutCredential(FLOW_TEST_SECRET);

    $setup = $this->call('POST', '/api/punchout/setup', content: flowTestSetupRequest(), server: ['CONTENT_TYPE' => 'text/xml']);

    $setup->assertOk();
    expect($setup->headers->get('Content-Type'))->toContain('text/xml');

    $document = new DOMDocument;
    expect($document->loadXML((string) $setup->getContent()))->toBeTrue();

    $startUrl = (string) $document->getElementsByTagName('URL')->item(0)?->textContent;
    $session = PunchoutSession::query()->sole();

    expect($startUrl)->toBe(route('punchout.start', ['token' => $session->token]))
        ->and($session->browser_form_post_url)->toBe('https://mwilczek-demo.coupacloud.com/punchout/checkout?id=2');

    $this->get($startUrl)->assertRedirect(route('storefront.catalog', ['token' => $session->token]));

    $this->get(route('storefront.catalog', ['token' => $session->token]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Catalog/Index'));
});

it('stores an escaped BrowserFormPost URL decoded, exactly as the buyer meant it', function () {
    createTestPunchoutCredential(FLOW_TEST_SECRET);

    $xml = str_replace(
        'https://mwilczek-demo.coupacloud.com/punchout/checkout?id=2',
        'https://buyer.example/cart/return?a=1&amp;b=2',
        flowTestSetupRequest(),
    );

    $this->call('POST', '/api/punchout/setup', content: $xml, server: ['CONTENT_TYPE' => 'text/xml'])->assertOk();

    expect(PunchoutSession::query()->sole()->browser_form_post_url)->toBe('https://buyer.example/cart/return?a=1&b=2');
});

it('answers a PunchOutSetupRequest carrying a raw "&" with a controlled cXML 400 and a located, secret-free diagnostic', function () {
    $logFile = flowTestLogFile();
    createTestPunchoutCredential(FLOW_TEST_SECRET);

    $xml = str_replace(
        '<Extrinsic name="BusinessUnit">COUPA</Extrinsic>',
        '<Extrinsic name="BusinessUnit">Carewell Health & Medical</Extrinsic>',
        flowTestSetupRequest(),
    );

    $response = $this->call('POST', '/api/punchout/setup', content: $xml, server: ['CONTENT_TYPE' => 'text/xml']);

    $response->assertStatus(400);
    expectControlledCxmlFailure((string) $response->getContent());
    expect($response->getContent())->toContain('code="400"');

    $log = PunchoutLog::query()->where('message_type', 'setup_request')->sole();

    expect($log->http_status)->toBe(400)
        ->and($log->error)->toContain('not well-formed XML')
        ->and($log->error)->toMatch('/line \d+, column \d+/')
        ->and($log->raw_payload)->toStartWith('[unparseable payload, not stored: sha256:');

    expect(PunchoutSession::query()->count())->toBe(0);
    expectNoSecretLeaked((string) $response->getContent(), $logFile);
});

it('answers an OrderRequest carrying a raw "&" with a controlled cXML 400 and creates no PurchaseOrder', function () {
    $logFile = flowTestLogFile();
    createTestPunchoutCredential(FLOW_TEST_SECRET);

    $response = $this->call('POST', '/api/punchout/order', content: flowTestOrderRequest(description: 'Gauze & Tape'), server: ['CONTENT_TYPE' => 'text/xml']);

    $response->assertStatus(400);
    expectControlledCxmlFailure((string) $response->getContent());

    $log = PunchoutLog::query()->where('message_type', 'order_request')->sole();

    expect($log->error)->toMatch('/not well-formed XML \(libxml \d+ at line \d+, column \d+/')
        ->and(PurchaseOrder::query()->count())->toBe(0);

    expectNoSecretLeaked((string) $response->getContent(), $logFile);
});

it('rejects a wrong shared secret on both endpoints without ever logging either secret', function () {
    $logFile = flowTestLogFile();
    createTestPunchoutCredential(FLOW_TEST_SECRET);
    $wrong = 'Wrong-Secret-Value-4b1c';

    $setup = $this->call('POST', '/api/punchout/setup', content: flowTestSetupRequest($wrong), server: ['CONTENT_TYPE' => 'text/xml']);
    $order = $this->call('POST', '/api/punchout/order', content: flowTestOrderRequest($wrong), server: ['CONTENT_TYPE' => 'text/xml']);

    $setup->assertStatus(401);
    $order->assertStatus(401);
    expect($setup->getContent())->toContain('code="401"')
        ->and($order->getContent())->toContain('code="401"')
        ->and(PunchoutSession::query()->count())->toBe(0)
        ->and(PurchaseOrder::query()->count())->toBe(0);

    foreach ([$setup, $order] as $response) {
        expectNoSecretLeaked((string) $response->getContent(), $logFile, $wrong);
        expectNoSecretLeaked((string) $response->getContent(), $logFile, FLOW_TEST_SECRET);
    }
});

it('accepts a valid OrderRequest at /api/punchout/order, the production PO transmission endpoint', function () {
    Queue::fake();
    createTestPunchoutCredential(FLOW_TEST_SECRET);

    $response = $this->call('POST', '/api/punchout/order', content: flowTestOrderRequest(description: 'Gauze &amp; Tape'), server: ['CONTENT_TYPE' => 'text/xml']);

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/xml')
        ->and($response->getContent())->toContain('code="200"');

    $purchaseOrder = PurchaseOrder::query()->where('po_number', 'PO-FLOW-1')->sole();

    expect($purchaseOrder->lines->first()?->description)->toBe('Gauze & Tape');
});

it('still answers an OrderRequest with cXML, not an HTML 500, when the inbound log write itself fails', function () {
    createTestPunchoutCredential(FLOW_TEST_SECRET);
    Schema::drop('punchout_logs');

    $response = $this->call('POST', '/api/punchout/order', content: flowTestOrderRequest(), server: ['CONTENT_TYPE' => 'text/xml']);

    $response->assertStatus(500);
    expectControlledCxmlFailure((string) $response->getContent());
    expect($response->getContent())->toContain('code="500"');
});

it('keeps /admin/punchout-preview/complete an admin-only preview, never a buyer endpoint', function () {
    Queue::fake();
    createTestPunchoutCredential(FLOW_TEST_SECRET);

    // A real buyer session returns its cart to the buyer's own
    // BrowserFormPost URL, never to the admin preview page.
    $this->call('POST', '/api/punchout/setup', content: flowTestSetupRequest(), server: ['CONTENT_TYPE' => 'text/xml'])->assertOk();

    expect(PunchoutSession::query()->sole()->browser_form_post_url)
        ->not->toBe(route('admin.punchout-preview.complete'));

    // An OrderRequest posted to the preview URL by a buyer's server gets
    // the admin login redirect, not a cXML acknowledgement, and no order.
    $this->call('POST', '/admin/punchout-preview/complete', content: flowTestOrderRequest(), server: ['CONTENT_TYPE' => 'text/xml'])
        ->assertRedirect('/admin/login');

    expect(PurchaseOrder::query()->count())->toBe(0);
});

it('is not a cXML endpoint at /storefront, which only serves the browser', function () {
    createTestPunchoutCredential(FLOW_TEST_SECRET);

    $response = $this->call('POST', '/storefront', content: flowTestSetupRequest(), server: ['CONTENT_TYPE' => 'text/xml']);

    $response->assertStatus(405);
    expect(PunchoutSession::query()->count())->toBe(0);
});
