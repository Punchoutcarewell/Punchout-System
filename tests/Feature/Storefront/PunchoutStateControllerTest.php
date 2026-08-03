<?php

declare(strict_types=1);

it('renders the no-token page for a direct visit that never carried a token', function () {
    $this->get('/storefront')
        ->assertRedirect(route('storefront.no-token'));

    $this->get(route('storefront.no-token'))
        ->assertInertia(fn ($page) => $page->component('Punchout/NoToken'));
});

it('renders the session-expired page for a token that does not resolve', function () {
    $this->get('/storefront?token=does-not-exist')
        ->assertRedirect(route('storefront.session-expired'));

    $this->get(route('storefront.session-expired'))
        ->assertInertia(fn ($page) => $page->component('Punchout/SessionExpired'));
});
