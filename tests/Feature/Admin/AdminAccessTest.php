<?php

declare(strict_types=1);

it('redirects a guest to the login page', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});

it('lets an authenticated user reach the dashboard', function () {
    actingAsAdmin();

    $this->get('/admin')->assertOk();
});
