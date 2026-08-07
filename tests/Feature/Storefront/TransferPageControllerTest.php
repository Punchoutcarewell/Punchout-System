<?php

declare(strict_types=1);

use App\Modules\Punchout\Models\PunchoutCredential;
use App\Modules\Punchout\Models\PunchoutLog;
use App\Modules\Punchout\Models\PunchoutSession;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

beforeEach(function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
});

it('builds the outbound order message, marks the session transferring (not transferred), and renders the transfer page', function () {
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

    // Not yet Transferred: the cXML PunchOut protocol has no callback
    // confirming the browser's form post to Coupa actually landed, so the
    // session stays resolvable within its grace window in case it needs
    // retrying. See PunchoutSessionStatus::Transferring.
    expect($session->fresh()->status->value)->toBe('transferring')
        ->and($session->fresh()->transferring_at)->not->toBeNull();
});

it('resumes a Transferring session by re-rendering the same already-built message, not sending a second one', function () {
    $session = issueTestPunchoutSession();
    $product = createTestProduct(['sku' => 'CW-4021', 'list_price' => '25.99', 'currency' => 'AUD']);

    $this->postJson("/storefront/cart-api/items?token={$session->token}", [
        'sku' => $product->sku,
        'quantity' => 2,
    ])->assertOk();

    $firstCxml = $this->post("/storefront/transfer?token={$session->token}")->inertiaProps('encodedCxml');

    // A reload/retry while still within the grace window: same session,
    // same route, no new cart-api call in between.
    $secondCxml = $this->post("/storefront/transfer?token={$session->token}")
        ->assertInertia(fn ($page) => $page->component('Punchout/TransferInProgress'))
        ->inertiaProps('encodedCxml');

    expect($secondCxml)->toBe($firstCxml);

    $orderMessageLogCount = PunchoutLog::query()
        ->where('session_id', $session->id)
        ->where('message_type', 'order_message')
        ->count();

    expect($orderMessageLogCount)->toBe(1);
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

it('renders the transfer-failed page naming the SKU when a product is deactivated after being added to an open cart', function () {
    $session = issueTestPunchoutSession();
    $product = createTestProduct(['sku' => 'CW-4021']);

    $this->postJson("/storefront/cart-api/items?token={$session->token}", [
        'sku' => $product->sku,
        'quantity' => 1,
    ])->assertOk();

    // The admin deactivates the product while it is still sitting in the
    // buyer's open cart: CartSnapshotFactory re-resolves pricing fresh at
    // transfer time and this is exactly what it now finds.
    $product->update(['is_active' => false]);

    $this->post("/storefront/transfer?token={$session->token}")
        ->assertInertia(fn ($page) => $page
            ->component('Punchout/TransferFailed')
            ->where('reason', fn (string $reason): bool => str_contains($reason, 'CW-4021'))
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
