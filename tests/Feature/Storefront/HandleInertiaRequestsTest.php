<?php

declare(strict_types=1);

use App\Shared\Models\SiteSetting;
use Illuminate\Support\Facades\Storage;
use Inertia\ResponseFactory;

it('includes cart on a full page visit', function () {
    $session = issueTestPunchoutSession();

    $this->get("/storefront?token={$session->token}")
        ->assertInertia(fn ($page) => $page->has('cart'));
});

it('shares siteLogoUrl as null when no logo has been configured', function () {
    $session = issueTestPunchoutSession();

    $this->get("/storefront?token={$session->token}")
        ->assertInertia(fn ($page) => $page->where('siteLogoUrl', null));
});

it('shares the configured site logo as a public disk URL', function () {
    Storage::fake('public');
    SiteSetting::current()->update(['logo_path' => 'branding/logo.png']);
    $session = issueTestPunchoutSession();

    $this->get("/storefront?token={$session->token}")
        ->assertInertia(fn ($page) => $page->where('siteLogoUrl', Storage::disk('public')->url('branding/logo.png')));
});

it('does not evaluate the cart summary on a partial reload that does not request it', function () {
    $session = issueTestPunchoutSession();

    // A first, real visit binds the session cookie the same way the
    // browser would; the partial reload below relies on that cookie
    // rather than the token query string, matching how Inertia's own
    // client issues partial reloads against the current URL.
    $this->get("/storefront?token={$session->token}");

    $response = $this->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => app(ResponseFactory::class)->getVersion(),
        'X-Inertia-Partial-Component' => 'Catalog/Index',
        'X-Inertia-Partial-Data' => 'punchoutSession',
    ])->get('/storefront');

    $response->assertOk();
    $props = $response->json('props');

    expect($props)->toHaveKey('punchoutSession')
        ->and($props)->not->toHaveKey('cart');
});
