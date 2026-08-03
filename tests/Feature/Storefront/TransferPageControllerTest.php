<?php

declare(strict_types=1);

use App\Modules\Punchout\Models\PunchoutCredential;
use App\Modules\Punchout\Models\PunchoutSession;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

beforeEach(function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
});

it('builds the outbound order message, marks the session transferred, and renders the transfer page', function () {
    $session = issueTestPunchoutSession();
    $product = createTestProduct(['sku' => 'CW-4021', 'list_price' => '25.99', 'currency' => 'AUD']);

    $this->postJson("/storefront/cart-api/items?token={$session->token}", [
        'sku' => $product->sku,
        'quantity' => 2,
    ])->assertOk();

    $this->post("/storefront/transfer?token={$session->token}")
        ->assertInertia(fn ($page) => $page
            ->component('Punchout/TransferInProgress')
            ->where('browserFormPostUrl', $session->browser_form_post_url)
            ->has('encodedCxml')
        );

    expect($session->fresh()->status->value)->toBe('transferred');
});

it('renders the transfer-failed page for an empty cart, without marking the session transferred', function () {
    $session = issueTestPunchoutSession();

    $this->post("/storefront/transfer?token={$session->token}")
        ->assertInertia(fn ($page) => $page
            ->component('Punchout/TransferFailed')
            ->has('reason')
        );

    expect($session->fresh()->status->value)->not->toBe('transferred');
});

it('renders the transfer-failed page when no active credential matches the outbound identity', function () {
    $session = issueTestPunchoutSession();
    $product = createTestProduct(['sku' => 'CW-4021']);

    $this->postJson("/storefront/cart-api/items?token={$session->token}", [
        'sku' => $product->sku,
        'quantity' => 1,
    ])->assertOk();

    PunchoutCredential::query()->update(['is_active' => false]);

    $this->post("/storefront/transfer?token={$session->token}")
        ->assertInertia(fn ($page) => $page
            ->component('Punchout/TransferFailed')
            ->has('reason')
        );

    expect(PunchoutSession::query()->firstOrFail()->status->value)->not->toBe('transferred');
});
