<?php

declare(strict_types=1);

it('renders the cart review page', function () {
    $session = issueTestPunchoutSession();

    $this->get("/storefront/cart?token={$session->token}")
        ->assertInertia(fn ($page) => $page->component('Cart/Review'));
});
