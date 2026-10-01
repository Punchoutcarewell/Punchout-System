<?php

declare(strict_types=1);

use App\Modules\Punchout\Contracts\SessionManagerInterface;
use App\Modules\Punchout\Enums\PunchoutSessionStatus;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

function previewCxml(string $buyerCookie): string
{
    return '<?xml version="1.0" encoding="UTF-8"?><cXML payloadID="1" timestamp="2026-10-01T00:00:00+00:00">'
        .'<Header/><Message><PunchOutOrderMessage><BuyerCookie>'.$buyerCookie.'</BuyerCookie>'
        .'<ItemIn quantity="1"><ItemID><SupplierPartID>CW-1</SupplierPartID></ItemID></ItemIn>'
        .'</PunchOutOrderMessage></Message></cXML>';
}

it('does not ask for an admin login', function () {
    $credential = createTestPunchoutCredential('ALD');
    $session = app(SessionManagerInterface::class)->startPreview($credential, 'No login');

    $response = $this->post('/admin/punchout-preview/complete', ['cxml-urlencoded' => previewCxml($session->buyer_cookie)]);

    $response->assertOk();
    expect($response->isRedirect())->toBeFalse();
});

it('returns the raw cXML as text/xml, not an HTML page', function () {
    $credential = createTestPunchoutCredential('ALD');
    $session = app(SessionManagerInterface::class)->startPreview($credential, 'Raw xml');

    $response = $this->post('/admin/punchout-preview/complete', ['cxml-urlencoded' => previewCxml($session->buyer_cookie)]);

    $response->assertOk();
    $body = (string) $response->getContent();

    expect($response->headers->get('Content-Type'))->toContain('text/xml')
        ->and($body)->toStartWith('<?xml')
        ->and($body)->not->toContain('<html')
        ->and($body)->toContain('<BuyerCookie>'.$session->buyer_cookie.'</BuyerCookie>')
        ->and($body)->toContain('CW-1')
        ->and(simplexml_load_string($body))->not->toBeFalse();
});

it('refuses cXML that does not belong to a preview session', function () {
    $this->post('/admin/punchout-preview/complete', ['cxml-urlencoded' => previewCxml('someone-elses-cookie')])
        ->assertForbidden();
});

it('refuses the BuyerCookie of a real, non-preview session', function () {
    $credential = createTestPunchoutCredential('ALD');
    $session = app(SessionManagerInterface::class)->startFromSharedSecret($credential);

    $response = $this->post('/admin/punchout-preview/complete', ['cxml-urlencoded' => previewCxml($session->buyer_cookie)]);

    $response->assertForbidden();
    expect($response->headers->get('Content-Type'))->toContain('text/xml')
        ->and($response->getContent())->toContain('code="403"');
});

it('accepts a secret-link session whose return URL points back at this endpoint, for testing', function () {
    $credential = createTestPunchoutCredential('ALD');
    $credential->update(['browser_form_post_url' => 'https://example.test/admin/punchout-preview/complete']);
    $session = app(SessionManagerInterface::class)->startFromSharedSecret($credential);

    $response = $this->post('/admin/punchout-preview/complete', ['cxml-urlencoded' => previewCxml($session->buyer_cookie)]);

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/xml')
        ->and($response->getContent())->toContain($session->buyer_cookie);
});

it('refuses missing or malformed cXML with a cXML fault', function (string $payload) {
    $response = $this->post('/admin/punchout-preview/complete', ['cxml-urlencoded' => $payload]);

    $response->assertStatus(400);
    expect($response->headers->get('Content-Type'))->toContain('text/xml')
        ->and($response->getContent())->toContain('code="400"');
})->with(['', '<cXML><oops & broken', 'plain text']);

it('completes a whole preview transaction on the generated session without any login', function () {
    $credential = createTestPunchoutCredential('ALD');
    $product = createTestProduct(['sku' => 'CW-4021', 'list_price' => '25.99', 'currency' => 'AUD']);
    $session = app(SessionManagerInterface::class)->startPreview($credential, 'End to end transfer');

    $this->withoutMiddleware(ValidateCsrfToken::class);

    $this->postJson("/storefront/cart-api/items?token={$session->token}", [
        'sku' => $product->sku,
        'quantity' => 2,
    ])->assertOk();

    $props = [];

    $this->post("/storefront/transfer?token={$session->token}")
        ->assertInertia(function ($page) use (&$props) {
            $page->component('Punchout/TransferInProgress');
            $props = $page->toArray()['props'];
        });

    expect($session->fresh()->status)->toBe(PunchoutSessionStatus::Transferring)
        ->and($props['browserFormPostUrl'])->toEndWith('/admin/punchout-preview/complete');

    // What the browser's auto-submitted form then does: post the cart to
    // the session's own browser_form_post_url, logged out.
    $response = $this->post('/admin/punchout-preview/complete', ['cxml-urlencoded' => $props['encodedCxml']]);

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/xml')
        ->and($response->getContent())->toContain('PunchOutOrderMessage')
        ->and($response->getContent())->toContain('CW-4021')
        ->and($response->getContent())->toContain($session->buyer_cookie);
});
