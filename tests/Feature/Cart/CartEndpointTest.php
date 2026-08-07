<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

beforeEach(function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
});

it('redirects a direct visit with no punchout session to the no-token page', function () {
    $this->get('/storefront/cart-api')->assertRedirect(route('storefront.no-token'));
});

it('returns an empty cart summary for a session with nothing added yet', function () {
    $session = issueTestPunchoutSession();

    $this->getJson("/storefront/cart-api?token={$session->token}")
        ->assertOk()
        ->assertJson(['lines' => [], 'itemCount' => 0]);
});

it('adds an item over the JSON API', function () {
    $session = issueTestPunchoutSession();
    $product = createTestProduct(['sku' => 'CW-4021', 'list_price' => '25.99', 'currency' => 'AUD']);

    $this->postJson("/storefront/cart-api/items?token={$session->token}", [
        'sku' => $product->sku,
        'quantity' => 2,
    ])
        ->assertOk()
        ->assertJsonPath('itemCount', 2)
        ->assertJsonPath('total.amount', '51.98')
        ->assertJsonPath('lines.0.sku', 'CW-4021');
});

it('returns a 404 for an unknown SKU', function () {
    $session = issueTestPunchoutSession();

    $this->postJson("/storefront/cart-api/items?token={$session->token}", [
        'sku' => 'DOES-NOT-EXIST',
        'quantity' => 1,
    ])
        ->assertStatus(404)
        ->assertJsonStructure(['message']);
});

it('returns a validation error for a zero quantity', function () {
    $session = issueTestPunchoutSession();
    $product = createTestProduct();

    $this->postJson("/storefront/cart-api/items?token={$session->token}", [
        'sku' => $product->sku,
        'quantity' => 0,
    ])->assertStatus(422);
});

it('returns a validation error for a quantity beyond the configured maximum', function () {
    $session = issueTestPunchoutSession();
    $product = createTestProduct();

    $this->postJson("/storefront/cart-api/items?token={$session->token}", [
        'sku' => $product->sku,
        'quantity' => 10_000,
    ])->assertStatus(422);
});

it('updates a line quantity over the JSON API', function () {
    $session = issueTestPunchoutSession();
    $product = createTestProduct(['sku' => 'CW-4021', 'list_price' => '10.00', 'currency' => 'AUD']);

    $this->postJson("/storefront/cart-api/items?token={$session->token}", ['sku' => $product->sku, 'quantity' => 1]);

    $this->patchJson("/storefront/cart-api/items/CW-4021?token={$session->token}", ['quantity' => 5])
        ->assertOk()
        ->assertJsonPath('lines.0.quantity', 5)
        ->assertJsonPath('total.amount', '50.00');
});

it('removes a line over the JSON API', function () {
    $session = issueTestPunchoutSession();
    $product = createTestProduct(['sku' => 'CW-4021']);

    $this->postJson("/storefront/cart-api/items?token={$session->token}", ['sku' => $product->sku, 'quantity' => 1]);

    $this->deleteJson("/storefront/cart-api/items/CW-4021?token={$session->token}")
        ->assertOk()
        ->assertJson(['lines' => [], 'itemCount' => 0]);
});

it('returns a 404 removing a SKU that was never added', function () {
    $session = issueTestPunchoutSession();
    $product = createTestProduct(['sku' => 'CW-4021']);

    $this->postJson("/storefront/cart-api/items?token={$session->token}", ['sku' => $product->sku, 'quantity' => 1]);

    $this->deleteJson("/storefront/cart-api/items/CW-NEVER-ADDED?token={$session->token}")
        ->assertStatus(404)
        ->assertJsonStructure(['message']);
});

it('updates and removes a line for a SKU containing characters that must be URL-encoded', function () {
    $session = issueTestPunchoutSession();
    $product = createTestProduct(['sku' => 'CW 40&21#a', 'list_price' => '10.00', 'currency' => 'AUD']);

    $this->postJson("/storefront/cart-api/items?token={$session->token}", ['sku' => $product->sku, 'quantity' => 1]);

    $encodedSku = rawurlencode($product->sku);

    $this->patchJson("/storefront/cart-api/items/{$encodedSku}?token={$session->token}", ['quantity' => 3])
        ->assertOk()
        ->assertJsonPath('lines.0.quantity', 3);

    $this->deleteJson("/storefront/cart-api/items/{$encodedSku}?token={$session->token}")
        ->assertOk()
        ->assertJson(['lines' => [], 'itemCount' => 0]);
});
