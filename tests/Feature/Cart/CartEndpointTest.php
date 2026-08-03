<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

beforeEach(function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
});

it('rejects a direct visit with no punchout session', function () {
    $this->getJson('/storefront/cart')->assertStatus(403);
});

it('returns an empty cart summary for a session with nothing added yet', function () {
    $session = issueTestPunchoutSession();

    $this->getJson("/storefront/cart?token={$session->token}")
        ->assertOk()
        ->assertJson(['lines' => [], 'item_count' => 0]);
});

it('adds an item over the JSON API', function () {
    $session = issueTestPunchoutSession();
    $product = createTestProduct(['sku' => 'CW-4021', 'list_price' => '25.99', 'currency' => 'AUD']);

    $this->postJson("/storefront/cart/items?token={$session->token}", [
        'sku' => $product->sku,
        'quantity' => 2,
    ])
        ->assertOk()
        ->assertJsonPath('item_count', 2)
        ->assertJsonPath('total', '51.98')
        ->assertJsonPath('lines.0.sku', 'CW-4021');
});

it('returns a 404 for an unknown SKU', function () {
    $session = issueTestPunchoutSession();

    $this->postJson("/storefront/cart/items?token={$session->token}", [
        'sku' => 'DOES-NOT-EXIST',
        'quantity' => 1,
    ])->assertStatus(404);
});

it('returns a validation error for a zero quantity', function () {
    $session = issueTestPunchoutSession();
    $product = createTestProduct();

    $this->postJson("/storefront/cart/items?token={$session->token}", [
        'sku' => $product->sku,
        'quantity' => 0,
    ])->assertStatus(422);
});

it('updates a line quantity over the JSON API', function () {
    $session = issueTestPunchoutSession();
    $product = createTestProduct(['sku' => 'CW-4021', 'list_price' => '10.00', 'currency' => 'AUD']);

    $this->postJson("/storefront/cart/items?token={$session->token}", ['sku' => $product->sku, 'quantity' => 1]);

    $this->patchJson("/storefront/cart/items/CW-4021?token={$session->token}", ['quantity' => 5])
        ->assertOk()
        ->assertJsonPath('lines.0.quantity', 5)
        ->assertJsonPath('total', '50.00');
});

it('removes a line over the JSON API', function () {
    $session = issueTestPunchoutSession();
    $product = createTestProduct(['sku' => 'CW-4021']);

    $this->postJson("/storefront/cart/items?token={$session->token}", ['sku' => $product->sku, 'quantity' => 1]);

    $this->deleteJson("/storefront/cart/items/CW-4021?token={$session->token}")
        ->assertOk()
        ->assertJson(['lines' => [], 'item_count' => 0]);
});

it('returns a 404 removing a SKU that was never added', function () {
    $session = issueTestPunchoutSession();
    $product = createTestProduct(['sku' => 'CW-4021']);

    $this->postJson("/storefront/cart/items?token={$session->token}", ['sku' => $product->sku, 'quantity' => 1]);

    $this->deleteJson("/storefront/cart/items/CW-NEVER-ADDED?token={$session->token}")
        ->assertStatus(404);
});
