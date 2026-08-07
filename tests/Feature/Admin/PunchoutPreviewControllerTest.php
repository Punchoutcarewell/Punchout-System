<?php

declare(strict_types=1);

use App\Modules\Punchout\Contracts\SessionManagerInterface;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

it('requires admin authentication', function () {
    $this->post('/admin/punchout-preview/complete', ['cxml-urlencoded' => '<cXML/>'])
        ->assertRedirect('/admin/login');
});

it('shows the posted cXML payload to an authenticated admin', function () {
    actingAsAdmin();

    $this->post('/admin/punchout-preview/complete', ['cxml-urlencoded' => '<cXML><Foo>bar</Foo></cXML>'])
        ->assertOk()
        ->assertSee('Preview transfer complete')
        ->assertSee('bar');
});

it('lets an admin complete a full preview transfer end to end, landing on the completion page', function () {
    actingAsAdmin();
    $credential = createTestPunchoutCredential('ALD');
    $product = createTestProduct(['sku' => 'CW-4021', 'list_price' => '25.99', 'currency' => 'AUD']);
    $session = app(SessionManagerInterface::class)->startPreview($credential, 'End to end transfer');

    $this->withoutMiddleware(ValidateCsrfToken::class);

    $this->postJson("/storefront/cart-api/items?token={$session->token}", [
        'sku' => $product->sku,
        'quantity' => 1,
    ])->assertOk();

    $this->post("/storefront/transfer?token={$session->token}")
        ->assertInertia(fn ($page) => $page->component('Punchout/TransferInProgress'));

    expect($session->fresh()->status->value)->toBe('transferring');
});
