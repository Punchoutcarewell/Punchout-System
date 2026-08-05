<?php

declare(strict_types=1);

function punchoutSetupXmlFrom(string $fromIdentity): string
{
    return <<<XML
    <?xml version="1.0" encoding="UTF-8"?>
    <!DOCTYPE cXML SYSTEM "http://xml.cxml.org/schemas/cXML/1.2.014/cXML.dtd">
    <cXML xml:lang="en-US" payloadID="1@coupahost.com" timestamp="2026-08-10T10:00:00-05:00">
      <Header>
        <From><Credential domain="DUNS"><Identity>{$fromIdentity}</Identity></Credential></From>
        <To><Credential domain="DUNS"><Identity>079928354</Identity></Credential></To>
        <Sender><Credential domain="DUNS"><Identity>{$fromIdentity}</Identity><SharedSecret>WRONG</SharedSecret></Credential></Sender>
      </Header>
      <Request>
        <PunchOutSetupRequest operation="create">
          <BuyerCookie>throttle-test</BuyerCookie>
          <BrowserFormPost><URL>https://example.coupacloud.com/punchout/checkout</URL></BrowserFormPost>
        </PunchOutSetupRequest>
      </Request>
    </cXML>
    XML;
}

it('rejects the 31st request within a minute from the same From identity with well-formed cXML, not HTML or JSON', function () {
    $xml = punchoutSetupXmlFrom('BUYER-THROTTLE-A');

    for ($i = 0; $i < 30; $i++) {
        $response = $this->call('POST', '/punchout/setup', content: $xml, server: ['CONTENT_TYPE' => 'text/xml']);
        expect($response->getStatusCode())->not->toBe(429);
    }

    $response = $this->call('POST', '/punchout/setup', content: $xml, server: ['CONTENT_TYPE' => 'text/xml']);

    $response->assertStatus(429);
    expect($response->getContent())->toContain('<?xml')
        ->and($response->getContent())->toContain('code="429"')
        ->and($response->getContent())->not->toContain('<html')
        ->and($response->headers->get('Content-Type'))->toContain('text/xml')
        ->and($response->headers->get('Retry-After'))->not->toBeNull();
});

it('keys the limit by the From identity, so a different buyer identity is not blocked by another buyer exhausting its own bucket', function () {
    $exhausted = punchoutSetupXmlFrom('BUYER-THROTTLE-B');

    for ($i = 0; $i < 31; $i++) {
        $this->call('POST', '/punchout/setup', content: $exhausted, server: ['CONTENT_TYPE' => 'text/xml']);
    }

    $this->call('POST', '/punchout/setup', content: $exhausted, server: ['CONTENT_TYPE' => 'text/xml'])
        ->assertStatus(429);

    $otherBuyer = punchoutSetupXmlFrom('BUYER-THROTTLE-C');

    $response = $this->call('POST', '/punchout/setup', content: $otherBuyer, server: ['CONTENT_TYPE' => 'text/xml']);

    expect($response->getStatusCode())->not->toBe(429);
});

it('falls back to keying by IP for a body it cannot parse, rather than skipping throttling entirely', function () {
    for ($i = 0; $i < 30; $i++) {
        $response = $this->call('POST', '/punchout/setup', content: '<not-xml', server: [
            'CONTENT_TYPE' => 'text/xml',
            'REMOTE_ADDR' => '203.0.113.50',
        ]);
        expect($response->getStatusCode())->not->toBe(429);
    }

    $this->call('POST', '/punchout/setup', content: '<not-xml', server: [
        'CONTENT_TYPE' => 'text/xml',
        'REMOTE_ADDR' => '203.0.113.50',
    ])->assertStatus(429);

    $response = $this->call('POST', '/punchout/setup', content: '<not-xml', server: [
        'CONTENT_TYPE' => 'text/xml',
        'REMOTE_ADDR' => '203.0.113.99',
    ]);

    expect($response->getStatusCode())->not->toBe(429);
});

it('applies the same throttling to /punchout/order, keyed independently of /punchout/setup', function () {
    $setupXml = punchoutSetupXmlFrom('BUYER-THROTTLE-D');

    for ($i = 0; $i < 30; $i++) {
        $this->call('POST', '/punchout/setup', content: $setupXml, server: ['CONTENT_TYPE' => 'text/xml']);
    }

    $this->call('POST', '/punchout/setup', content: $setupXml, server: ['CONTENT_TYPE' => 'text/xml'])
        ->assertStatus(429);

    $orderXml = <<<'XML'
    <?xml version="1.0" encoding="UTF-8"?>
    <!DOCTYPE cXML SYSTEM "http://xml.cxml.org/schemas/cXML/1.2.014/cXML.dtd">
    <cXML xml:lang="en-US" payloadID="1@coupahost.com" timestamp="2026-08-10T10:00:00-05:00">
      <Header>
        <From><Credential domain="DUNS"><Identity>BUYER-THROTTLE-D</Identity></Credential></From>
        <To><Credential domain="DUNS"><Identity>079928354</Identity></Credential></To>
        <Sender><Credential domain="DUNS"><Identity>BUYER-THROTTLE-D</Identity></Credential></Sender>
      </Header>
      <Request deploymentMode="test">
        <OrderRequest>
          <OrderRequestHeader orderID="PO-THROTTLE" orderDate="2026-08-10T10:00:00-05:00" type="new">
            <Total><Money currency="AUD">1.00</Money></Total>
          </OrderRequestHeader>
        </OrderRequest>
      </Request>
    </cXML>
    XML;

    $response = $this->call('POST', '/punchout/order', content: $orderXml, server: ['CONTENT_TYPE' => 'text/xml']);

    expect($response->getStatusCode())->not->toBe(429);
});
