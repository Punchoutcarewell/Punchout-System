<?php

declare(strict_types=1);

it('renders the product detail page with contract price', function () {
    $session = issueTestPunchoutSession();
    createTestProduct(['sku' => 'CW-4021', 'list_price' => '29.99', 'currency' => 'AUD']);

    $this->get("/storefront/products/CW-4021?token={$session->token}")
        ->assertInertia(fn ($page) => $page
            ->component('Product/Show')
            ->where('product.sku', 'CW-4021')
            ->where('contractPrice.amount', '29.99')
        );
});

it('returns a 404 for an unknown SKU', function () {
    $session = issueTestPunchoutSession();

    $this->get("/storefront/products/DOES-NOT-EXIST?token={$session->token}")->assertStatus(404);
});
